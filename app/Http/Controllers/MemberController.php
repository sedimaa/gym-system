<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeactivateMemberRequest;
use App\Http\Requests\MemberRequest;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Member::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,active,inactive'],
        ]);
        $status = $filters['status'] ?? 'active';

        $members = Member::query()
            ->search($filters['q'] ?? null)
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return view('members.index', [
            'members' => $members,
            'q' => $filters['q'] ?? '',
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Member::class);

        return view('members.create', ['member' => new Member(['joined_on' => today()])]);
    }

    public function store(MemberRequest $request): RedirectResponse
    {
        $member = Member::create($request->validated());

        return redirect()->route('members.show', $member)
            ->with('success', "{$member->full_name} registered as {$member->member_code}.");
    }

    public function show(Member $member): View
    {
        Gate::authorize('view', $member);

        return view('members.show', ['member' => $member]);
    }

    public function edit(Member $member): View
    {
        Gate::authorize('update', $member);

        return view('members.edit', ['member' => $member]);
    }

    public function update(MemberRequest $request, Member $member): RedirectResponse
    {
        $member->update($request->validated());

        return redirect()->route('members.show', $member)->with('success', 'Member details saved.');
    }

    public function deactivate(DeactivateMemberRequest $request, Member $member): RedirectResponse
    {
        $member->update([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivation_reason' => $request->validated('deactivation_reason'),
        ]);

        return redirect()->route('members.show', $member)->with('success', "{$member->full_name} has been deactivated. All records are kept.");
    }

    public function reactivate(Member $member): RedirectResponse
    {
        Gate::authorize('deactivate', $member);

        $member->update(['is_active' => true, 'deactivated_at' => null, 'deactivation_reason' => null]);

        return redirect()->route('members.show', $member)->with('success', "{$member->full_name} has been reactivated.");
    }

    public function destroy(Member $member): RedirectResponse
    {
        Gate::authorize('delete', $member);

        if ($member->hasHistory()) {
            return back()->with('error', "{$member->full_name} has payments or attendance on record, so the member can't be deleted. Deactivate the member instead.");
        }

        $member->delete();

        return redirect()->route('members.index')->with('success', "{$member->full_name} ({$member->member_code}) deleted.");
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Http\Requests\DeactivateMemberRequest;
use App\Http\Requests\MemberRequest;
use App\Models\Member;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function __construct(private readonly MembershipService $memberships) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Member::class);

        $statusOptions = [
            'current' => 'All current members',
            MembershipStatus::Active->value => 'Active',
            MembershipStatus::Expiring->value => 'Expiring soon',
            MembershipStatus::Expired->value => 'Expired',
            MembershipStatus::None->value => 'No plan yet',
            MembershipStatus::Inactive->value => 'Deactivated',
            'all' => 'Everyone (incl. deactivated)',
        ];

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', array_keys($statusOptions))],
        ]);
        $status = $filters['status'] ?? 'current';

        $query = Member::query()->search($filters['q'] ?? null)->with('currentMembership.plan');

        match ($status) {
            'all' => null,
            'current' => $query->where('is_active', true),
            default => $this->memberships->scopeToStatus($query, MembershipStatus::from($status)),
        };

        $members = $query->orderBy('last_name')->orderBy('first_name')->paginate(20)->withQueryString();

        return view('members.index', [
            'members' => $members,
            'statuses' => $members->getCollection()->mapWithKeys(fn (Member $m) => [$m->id => $this->memberships->status($m)]),
            'showMembershipColumns' => true,
            'statusOptions' => $statusOptions,
            'q' => $filters['q'] ?? '',
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Member::class);

        return view('members.create', ['member' => new Member(['joined_on' => today()]), 'gymRules' => setting('gym_rules')]);
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

        $member->load(['currentMembership.plan', 'memberships' => fn ($q) => $q->with(['plan', 'payment'])->latest('id')]);

        return view('members.show', [
            'member' => $member,
            'status' => $this->memberships->status($member),
            'payments' => $member->payments()->with(['plan', 'receiver'])->latest('paid_on')->latest('id')->limit(50)->get(),
            'recentAttendance' => $member->attendance()->with('recorder')->latest('checked_in_at')->limit(15)->get(),
            'visitsThisMonth' => $member->attendance()->visits()->betweenDates(today()->startOfMonth()->toDateString(), today()->toDateString())->count(),
        ]);
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

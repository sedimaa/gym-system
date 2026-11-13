<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreStaffAccountRequest;
use App\Http\Requests\UpdateStaffAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin-only management of login accounts (one per staff member, no shared logins).
 * Accounts are disabled rather than deleted so their history (payments, logs) stays intact.
 */
class StaffAccountController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('staff-accounts.index', [
            'accounts' => User::orderByDesc('is_active')->orderBy('role')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('staff-accounts.create', ['roles' => Role::cases()]);
    }

    public function store(StoreStaffAccountRequest $request): RedirectResponse
    {
        $account = User::create($request->validated() + ['is_active' => true]);

        return redirect()->route('staff-accounts.index')
            ->with('success', "Account for {$account->name} created.");
    }

    public function edit(User $account): View
    {
        Gate::authorize('update', $account);

        return view('staff-accounts.edit', ['account' => $account, 'roles' => Role::cases()]);
    }

    public function update(UpdateStaffAccountRequest $request, User $account): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $account->update($data);

        return redirect()->route('staff-accounts.index')
            ->with('success', "Account for {$account->name} updated.");
    }

    public function toggleActive(User $account): RedirectResponse
    {
        Gate::authorize('toggleActive', $account);

        $account->update(['is_active' => ! $account->is_active]);

        $state = $account->is_active ? 'enabled' : 'disabled';

        return redirect()->route('staff-accounts.index')
            ->with('success', "Account for {$account->name} {$state}.");
    }
}

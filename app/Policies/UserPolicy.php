<?php

namespace App\Policies;

use App\Models\User;

/**
 * Staff accounts: admin only. An admin can never disable or demote themselves,
 * so the gym can't be locked out of its own system.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $account): bool
    {
        return $user->isAdmin();
    }

    public function toggleActive(User $user, User $account): bool
    {
        return $user->isAdmin() && ! $user->is($account);
    }
}

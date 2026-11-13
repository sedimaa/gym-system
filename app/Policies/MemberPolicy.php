<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

/**
 * Staff and admin can view, register and edit members.
 * Only the admin deactivates/reactivates (gate: deactivate-members) or deletes (gate: delete-records).
 */
class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Member $member): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Member $member): bool
    {
        return true;
    }

    public function deactivate(User $user, Member $member): bool
    {
        return $user->can('deactivate-members');
    }

    public function delete(User $user, Member $member): bool
    {
        return $user->can('delete-records');
    }
}

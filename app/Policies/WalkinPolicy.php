<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Walkin;

/** Staff and admin record walk-ins; only the admin deletes them. */
class WalkinPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Walkin $walkin): bool
    {
        return $user->can('delete-records');
    }
}

<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/** Staff and admin record payments; only the admin deletes them. */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Payment $payment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->can('delete-records');
    }
}

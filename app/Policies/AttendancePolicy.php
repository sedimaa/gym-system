<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

/**
 * Staff and admin check members in (allow / deny / override with note).
 * Adding or correcting records afterwards is admin-only (gate: correct-attendance);
 * deleting needs delete-records.
 */
class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function addRecord(User $user): bool
    {
        return $user->can('correct-attendance');
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $user->can('correct-attendance');
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->can('delete-records');
    }
}

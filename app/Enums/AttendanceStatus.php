<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Allowed = 'allowed';
    case Denied = 'denied';
    case Manual = 'manual'; // staff override with a note, or a record added by the admin

    public function label(): string
    {
        return match ($this) {
            self::Allowed => 'Checked in',
            self::Denied => 'Denied',
            self::Manual => 'Override',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Allowed => 'badge-green',
            self::Denied => 'badge-red',
            self::Manual => 'badge-amber',
        };
    }

    /** Counts as a visit (denied attempts don't). */
    public function isVisit(): bool
    {
        return $this !== self::Denied;
    }
}

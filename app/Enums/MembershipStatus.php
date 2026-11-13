<?php

namespace App\Enums;

/** Computed from the current membership's expiry date; never stored. */
enum MembershipStatus: string
{
    case Active = 'active';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case Inactive = 'inactive'; // member deactivated (overrides the others)
    case None = 'none';         // registered but has never paid for a plan

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Expiring => 'Expiring soon',
            self::Expired => 'Expired',
            self::Inactive => 'Deactivated',
            self::None => 'No plan yet',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'badge-green',
            self::Expiring => 'badge-amber',
            self::Expired => 'badge-red',
            self::Inactive => 'badge-gray',
            self::None => 'badge-slate',
        };
    }

    /** May check in without staff override. */
    public function allowsCheckIn(): bool
    {
        return in_array($this, [self::Active, self::Expiring], true);
    }
}

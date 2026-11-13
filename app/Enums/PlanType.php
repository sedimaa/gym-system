<?php

namespace App\Enums;

enum PlanType: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
    case PerSession = 'per_session';
    case DayPass = 'day_pass';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Yearly => 'Yearly',
            self::PerSession => 'Per-session bundle',
            self::DayPass => 'Day pass (walk-in)',
        };
    }

    /** Suggested validity when creating a plan of this type. */
    public function defaultDurationDays(): int
    {
        return match ($this) {
            self::Monthly => 30,
            self::Quarterly => 90,
            self::Yearly => 365,
            self::PerSession => 60,
            self::DayPass => 1,
        };
    }

    /** Day passes are sold to walk-ins, not attached to members. */
    public function isForMembers(): bool
    {
        return $this !== self::DayPass;
    }
}

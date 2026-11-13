<?php

namespace App\Support;

class Money
{
    /**
     * Format an amount as Philippine peso, e.g. ₱1,234.00.
     * Amounts are stored as DECIMAL(10,2), which Eloquent returns as strings.
     */
    public static function peso(int|float|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);
        $sign = $value < 0 ? '-' : '';

        return $sign.'₱'.number_format(abs($value), 2);
    }
}

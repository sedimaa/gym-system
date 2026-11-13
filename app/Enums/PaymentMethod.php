<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case GCash = 'gcash';
    case Maya = 'maya';
    case BankTransfer = 'bank_transfer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::GCash => 'GCash',
            self::Maya => 'Maya',
            self::BankTransfer => 'Bank transfer',
            self::Other => 'Other',
        };
    }

    /** E-wallet and bank payments should carry a reference number. */
    public function needsReference(): bool
    {
        return $this !== self::Cash;
    }
}

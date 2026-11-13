<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\User;
use App\Models\Walkin;
use App\Support\Settings;
use InvalidArgumentException;

/**
 * Walk-ins pay the day-pass price. The amount can only differ from it when the
 * walkin_price_editable setting allows it. Date and time come from the server clock.
 */
class WalkinService
{
    public function __construct(private readonly Settings $settings) {}

    public function dayPass(): ?Plan
    {
        return Plan::currentDayPass();
    }

    public function priceIsEditable(): bool
    {
        return (bool) $this->settings->get('walkin_price_editable');
    }

    /** @param  array{name?: ?string, phone?: ?string, amount?: mixed, method: string, reference_no?: ?string, notes?: ?string}  $data */
    public function record(array $data, User $recordedBy): Walkin
    {
        $dayPass = $this->dayPass() ?? throw new InvalidArgumentException('There is no active day-pass plan. The admin can add one under Plans & prices.');

        $amount = $this->priceIsEditable() && isset($data['amount']) && $data['amount'] !== ''
            ? $data['amount']
            : $dayPass->price;

        return Walkin::create([
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'plan_id' => $dayPass->id,
            'amount' => $amount,
            'method' => $data['method'],
            'reference_no' => $data['reference_no'] ?? null,
            'visited_at' => now(),
            'recorded_by' => $recordedBy->id,
            'notes' => $data['notes'] ?? null,
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Membership extends Model
{
    use HasFactory;

    protected $fillable = ['member_id', 'plan_id', 'start_date', 'expiry_date', 'sessions_total', 'sessions_remaining'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'expiry_date' => 'date',
            'sessions_total' => 'integer',
            'sessions_remaining' => 'integer',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function isPerSession(): bool
    {
        return $this->sessions_total !== null;
    }

    /** Whole days from today until expiry (negative once expired). */
    public function daysLeft(): int
    {
        return (int) today()->diffInDays($this->expiry_date, false);
    }
}

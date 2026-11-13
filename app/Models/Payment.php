<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'member_id', 'membership_id', 'plan_id', 'kind', 'amount', 'method',
        'reference_no', 'paid_on', 'received_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'paid_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->receipt_no ??= static::nextReceiptNo();
        });
    }

    public static function nextReceiptNo(): string
    {
        $number = (int) static::max('id') + 1;

        do {
            $receipt = sprintf('OR-%06d', $number++);
        } while (static::where('receipt_no', $receipt)->exists());

        return $receipt;
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function scopeBetween(Builder $query, string $from, string $to): void
    {
        $query->whereDate('paid_on', '>=', $from)->whereDate('paid_on', '<=', $to);
    }

    public function kindLabel(): string
    {
        return $this->kind === 'new' ? 'New' : 'Renewal';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('payments')
            ->logOnly(['receipt_no', 'member.member_code', 'plan.name', 'kind', 'amount', 'method', 'reference_no', 'paid_on'])
            ->dontSubmitEmptyLogs();
    }
}

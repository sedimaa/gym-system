<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Walkin extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'phone', 'plan_id', 'amount', 'method', 'reference_no', 'visited_at', 'recorded_by', 'notes'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'visited_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeOnDate(Builder $query, string $date): void
    {
        $query->whereDate('visited_at', $date);
    }

    public function scopeBetweenDates(Builder $query, string $from, string $to): void
    {
        $query->whereDate('visited_at', '>=', $from)->whereDate('visited_at', '<=', $to);
    }

    public function displayName(): string
    {
        return $this->name ?: 'Walk-in guest';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('walkins')
            ->logOnly(['name', 'amount', 'method', 'reference_no', 'visited_at'])
            ->dontSubmitEmptyLogs();
    }
}

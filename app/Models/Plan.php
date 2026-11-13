<?php

namespace App\Models;

use App\Enums\PlanType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Plan extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'type', 'price', 'duration_days', 'session_count', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => PlanType::class,
            'price' => 'decimal:2',
            'duration_days' => 'integer',
            'session_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function walkins(): HasMany
    {
        return $this->hasMany(Walkin::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Plans a member can pay for (everything except day passes). */
    public function scopeForMembers(Builder $query): void
    {
        $query->where('type', '!=', PlanType::DayPass->value);
    }

    public function scopeDayPass(Builder $query): void
    {
        $query->where('type', PlanType::DayPass->value);
    }

    /** The day-pass plan used to price walk-ins (the first active one). */
    public static function currentDayPass(): ?self
    {
        return static::dayPass()->active()->orderBy('id')->first();
    }

    public function isPerSession(): bool
    {
        return $this->type === PlanType::PerSession;
    }

    /** A plan that has been sold can't be deleted (history would break); deactivate it instead. */
    public function isInUse(): bool
    {
        return $this->memberships()->exists() || $this->walkins()->exists();
    }

    public function durationLabel(): string
    {
        $days = $this->duration_days;

        return $days === 1 ? '1 day' : "{$days} days";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('plans')
            ->logOnly(['name', 'type', 'price', 'duration_days', 'session_count', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}

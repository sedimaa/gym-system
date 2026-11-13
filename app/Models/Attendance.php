<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = ['member_id', 'membership_id', 'checked_in_at', 'status', 'source', 'session_used', 'note', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'status' => AttendanceStatus::class,
            'session_used' => 'boolean',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeOnDate(Builder $query, string $date): void
    {
        $query->whereDate('checked_in_at', $date);
    }

    public function scopeBetweenDates(Builder $query, string $from, string $to): void
    {
        $query->whereDate('checked_in_at', '>=', $from)->whereDate('checked_in_at', '<=', $to);
    }

    /** Allowed and override entries (not denied attempts). */
    public function scopeVisits(Builder $query): void
    {
        $query->whereIn('status', [AttendanceStatus::Allowed->value, AttendanceStatus::Manual->value]);
    }
}

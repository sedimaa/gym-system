<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Member extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'gender', 'birthdate', 'phone', 'email', 'address',
        'emergency_contact_name', 'emergency_contact_phone', 'notes', 'joined_on',
        'is_active', 'deactivated_at', 'deactivation_reason',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'joined_on' => 'date',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Member $member) {
            $member->member_code ??= static::nextMemberCode();
            $member->joined_on ??= today();
        });
    }

    /** Next free code in the GYM-0001 format. */
    public static function nextMemberCode(): string
    {
        $number = (int) static::max('id') + 1;

        do {
            $code = sprintf('GYM-%04d', $number++);
        } while (static::where('member_code', $code)->exists());

        return $code;
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(function () {
            $middle = $this->middle_name ? ' '.mb_substr($this->middle_name, 0, 1).'.' : '';

            return "{$this->first_name}{$middle} {$this->last_name}";
        });
    }

    protected function initials(): Attribute
    {
        return Attribute::get(fn () => mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1)));
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** The latest paid period; status is computed from it (see MembershipService). */
    public function currentMembership(): HasOne
    {
        return $this->hasOne(Membership::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function age(): ?int
    {
        return $this->birthdate?->age;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Each word must match the code, a name part or the phone number. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        foreach (preg_split('/\s+/', trim((string) $term), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $word).'%';
            $query->where(fn (Builder $q) => $q
                ->where('member_code', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('middle_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('phone', 'like', $like));
        }
    }

    /** True once the member has payments or attendance; such members are deactivated, not deleted. */
    public function hasHistory(): bool
    {
        return $this->payments()->exists(); // attendance is added in Module 6
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('members')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}

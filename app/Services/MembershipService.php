<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * All membership rules live here (CLAUDE.md → Business rules):
 *  - status is computed from the current (latest) membership, never stored
 *  - expiry date = start date + plan duration_days
 *  - renewal start follows the renewal_start_mode setting; plan changes start on the payment date
 *  - saving a payment creates the membership in the same transaction
 */
class MembershipService
{
    public function __construct(private readonly Settings $settings) {}

    // ---------------------------------------------------------------- status

    public function status(Member $member, ?CarbonInterface $today = null): MembershipStatus
    {
        if (! $member->is_active) {
            return MembershipStatus::Inactive;
        }

        $current = $member->currentMembership;

        return $current
            ? $this->membershipStatus($current, $today)
            : MembershipStatus::None;
    }

    /** Status of one membership period, ignoring whether the member is deactivated. */
    public function membershipStatus(Membership $membership, ?CarbonInterface $today = null): MembershipStatus
    {
        $today = CarbonImmutable::parse($today ?? today())->startOfDay();
        $expiry = CarbonImmutable::parse($membership->expiry_date)->startOfDay();

        // A per-session bundle with no sessions left is used up, whatever its date.
        if ($membership->sessions_remaining !== null && $membership->sessions_remaining <= 0) {
            return MembershipStatus::Expired;
        }

        if ($today->greaterThan($expiry->addDays($this->graceDays()))) {
            return MembershipStatus::Expired;
        }

        if ($expiry->subDays($this->expiringSoonDays())->lessThanOrEqualTo($today)) {
            return MembershipStatus::Expiring;
        }

        return MembershipStatus::Active;
    }

    /**
     * Restrict a members query to one computed status. Mirrors membershipStatus() in SQL
     * so lists, counts and filters agree with what each profile shows.
     */
    public function scopeToStatus(Builder $members, MembershipStatus $status, ?CarbonInterface $today = null): Builder
    {
        $today = CarbonImmutable::parse($today ?? today())->startOfDay();
        $graceCutoff = $today->subDays($this->graceDays())->toDateString();   // expiry before this = expired
        $soonCutoff = $today->addDays($this->expiringSoonDays())->toDateString(); // expiry up to this = expiring

        // whereDate: compare calendar days only (SQLite stores dates with a time part).
        $hasSessions = fn (Builder $q) => $q->whereNull('sessions_remaining')->orWhere('sessions_remaining', '>', 0);

        return match ($status) {
            MembershipStatus::Inactive => $members->where('members.is_active', false),

            MembershipStatus::None => $members->where('members.is_active', true)->doesntHave('memberships'),

            MembershipStatus::Expired => $members->where('members.is_active', true)
                ->whereHas('currentMembership', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->whereDate('expiry_date', '<', $graceCutoff)
                    ->orWhere('sessions_remaining', '<=', 0))),

            MembershipStatus::Expiring => $members->where('members.is_active', true)
                ->whereHas('currentMembership', fn (Builder $q) => $q
                    ->whereDate('expiry_date', '>=', $graceCutoff)
                    ->whereDate('expiry_date', '<=', $soonCutoff)
                    ->where($hasSessions)),

            MembershipStatus::Active => $members->where('members.is_active', true)
                ->whereHas('currentMembership', fn (Builder $q) => $q
                    ->whereDate('expiry_date', '>', $soonCutoff)
                    ->where($hasSessions)),
        };
    }

    /** @return array<string, int> count of members per status value */
    public function statusCounts(?CarbonInterface $today = null): array
    {
        return collect(MembershipStatus::cases())
            ->mapWithKeys(fn (MembershipStatus $s) => [$s->value => $this->scopeToStatus(Member::query(), $s, $today)->count()])
            ->all();
    }

    // -------------------------------------------------------------- payments

    /**
     * When a new period for $plan, paid on $paidOn, should start.
     * Renewing the same plan honours renewal_start_mode; changing plans always starts on the payment date.
     */
    public function startDateFor(?Membership $previous, Plan $plan, CarbonInterface $paidOn): CarbonImmutable
    {
        $paidOn = CarbonImmutable::parse($paidOn)->startOfDay();

        $isSamePlanRenewal = $previous && $previous->plan_id === $plan->id;
        $previousStillRunning = $previous
            && CarbonImmutable::parse($previous->expiry_date)->greaterThanOrEqualTo($paidOn)
            && ($previous->sessions_remaining === null || $previous->sessions_remaining > 0);

        if ($isSamePlanRenewal && $previousStillRunning && $this->renewalStartMode() === 'from_old_expiry') {
            return CarbonImmutable::parse($previous->expiry_date)->addDay();
        }

        return $paidOn;
    }

    public function expiryDateFor(Plan $plan, CarbonInterface $start): CarbonImmutable
    {
        return CarbonImmutable::parse($start)->addDays($plan->duration_days);
    }

    /**
     * Record a member payment and create the membership period it buys, in one transaction.
     *
     * @param  array{amount: numeric, method: string, reference_no?: ?string, notes?: ?string, paid_on?: mixed}  $data
     */
    public function recordPayment(Member $member, Plan $plan, array $data, User $receivedBy): Payment
    {
        if (! $member->is_active) {
            throw new InvalidArgumentException('Deactivated members must be reactivated before they can pay.');
        }

        if (! $plan->type->isForMembers()) {
            throw new InvalidArgumentException('Day passes are recorded as walk-ins, not member payments.');
        }

        return DB::transaction(function () use ($member, $plan, $data, $receivedBy) {
            $paidOn = CarbonImmutable::parse($data['paid_on'] ?? today())->startOfDay();
            // Lock the member row so two payments for the same member can't interleave.
            Member::whereKey($member->id)->lockForUpdate()->first();
            $previous = $member->currentMembership()->first();

            $start = $this->startDateFor($previous, $plan, $paidOn);

            $membership = $member->memberships()->create([
                'plan_id' => $plan->id,
                'start_date' => $start,
                'expiry_date' => $this->expiryDateFor($plan, $start),
                'sessions_total' => $plan->session_count,
                'sessions_remaining' => $plan->session_count,
            ]);

            $payment = Payment::create([
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'plan_id' => $plan->id,
                'kind' => $member->memberships()->count() > 1 ? 'renewal' : 'new',
                'amount' => $data['amount'],
                'method' => $data['method'],
                'reference_no' => $data['reference_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_on' => $paidOn,
                'received_by' => $receivedBy->id,
            ]);

            $member->unsetRelation('currentMembership');

            return $payment;
        });
    }

    /**
     * Only the member's latest payment can be removed, so the chain of periods stays consistent.
     * Removing it also removes the membership period it created.
     */
    public function canDeletePayment(Payment $payment): bool
    {
        return $payment->membership_id === $payment->member->currentMembership?->id;
    }

    public function deletePayment(Payment $payment): void
    {
        if (! $this->canDeletePayment($payment)) {
            throw new InvalidArgumentException('Only the latest payment of a member can be deleted.');
        }

        DB::transaction(function () use ($payment) {
            $membership = $payment->membership;
            $payment->delete();
            $membership?->delete();
        });
    }

    // -------------------------------------------------------------- settings

    public function graceDays(): int
    {
        return max(0, (int) $this->settings->get('grace_days'));
    }

    public function expiringSoonDays(): int
    {
        return max(0, (int) $this->settings->get('expiring_soon_days'));
    }

    public function renewalStartMode(): string
    {
        return $this->settings->get('renewal_start_mode') === 'from_old_expiry' ? 'from_old_expiry' : 'from_payment_date';
    }
}

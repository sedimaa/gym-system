<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\PlanType;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\User;
use App\Models\Walkin;
use App\Services\MembershipService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ~40 Filipino-named members with about 3 months of payments, attendance and walk-ins.
 * Payments go through MembershipService, so memberships, receipts and renewals follow the real rules.
 * The mix gives active, expiring-soon, expired, no-plan and deactivated members as of today.
 * Deterministic (fixed random seed), relative to the day it is run.
 */
class DemoDataSeeder extends Seeder
{
    private const WINDOW_DAYS = 90;

    private const FIRST_MALE = ['Juan', 'Jose', 'Mark Anthony', 'John Paul', 'Christian', 'Jerome', 'Rommel', 'Paolo', 'Carlo', 'Miguel', 'Angelo', 'Jericho', 'Ramon', 'Kevin', 'Joshua', 'Renato', 'Arnel', 'Dennis', 'Ronaldo', 'Gabriel', 'Nathaniel'];

    private const FIRST_FEMALE = ['Maria Clara', 'Ana', 'Kristine', 'Mary Grace', 'Princess', 'Angelica', 'Rowena', 'Joanna', 'Camille', 'Rhea', 'Cristina', 'Michelle', 'Jasmine', 'Liza', 'Bea', 'Patricia', 'Nicole', 'Shiela', 'Erlinda'];

    private const LAST = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Tomas', 'Andrada', 'Castillo', 'Flores', 'Villanueva', 'Ramos', 'Castro', 'Rivera', 'Aquino', 'Navarro', 'Salazar', 'Mercado', 'De Leon', 'Dela Cruz', 'Gonzales', 'Lopez', 'Pascual', 'Domingo', 'Manalo', 'Soriano', 'Panganiban', 'Macaraeg', 'Dimaculangan', 'Agbayani', 'Evangelista', 'Galang', 'Samonte', 'Valdez', 'Lim', 'Tan', 'Bernardo', 'Esguerra'];

    private const PUROKS = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6'];

    private const STREETS = ['Rizal St.', 'Mabini St.', 'Bonifacio St.', 'Luna St.', 'Del Pilar St.', 'Sampaguita St.', 'Ilang-Ilang St.', 'Narra St.'];

    private const PREFIXES = ['0917', '0918', '0920', '0927', '0935', '0945', '0956', '0966', '0977', '0998'];

    private CarbonImmutable $today;

    private CarbonImmutable $windowStart;

    private CarbonImmutable $realNow; // the seeder fakes the clock while recording payments

    /** @var array<string, Plan> */
    private array $plans = [];

    private User $admin;

    private User $staff;

    public function run(): void
    {
        mt_srand(2026);
        activity()->disableLogging();
        $clockBefore = Carbon::getTestNow(); // null outside tests

        $this->realNow = CarbonImmutable::now(config('app.timezone'));
        $this->today = $this->realNow->startOfDay();
        $this->windowStart = $this->today->subDays(self::WINDOW_DAYS);
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->staff = User::where('role', 'staff')->firstOrFail();
        $this->plans = Plan::all()->keyBy(fn (Plan $p) => $p->type->value)->all();

        DB::transaction(function () {
            $profiles = $this->memberProfiles();
            $members = $this->createMembers($profiles);
            $this->recordPayments($members, $profiles);
            $this->deactivate($members, $profiles);
            $this->recordAttendance($members, $profiles);
            $this->recordWalkins();
        });

        Carbon::setTestNow($clockBefore); // back to the real (or test) clock
        activity()->enableLogging();
    }

    /**
     * What each demo member looks like today: target status, plan and how often they come.
     *
     * @return list<array{status: string, plan: string, frequency: float, renewer: bool}>
     */
    private function memberProfiles(): array
    {
        $mix = array_merge(
            array_fill(0, 23, 'active'),
            array_fill(0, 6, 'expiring'),
            array_fill(0, 6, 'expired'),
            array_fill(0, 2, 'none'),
            array_fill(0, 2, 'inactive'),
            ['bundle_used_up'],
        );

        return array_map(function (string $status) {
            $roll = mt_rand(1, 100);
            $plan = match (true) {
                $status === 'bundle_used_up' => PlanType::PerSession->value,
                $roll <= 58 => PlanType::Monthly->value,
                $roll <= 74 => PlanType::Quarterly->value,
                $roll <= 80 => PlanType::Yearly->value,
                default => PlanType::PerSession->value,
            };

            return [
                'status' => $status,
                'plan' => $plan,
                'frequency' => mt_rand(20, 75) / 100, // chance of a visit on a given day
                'renewer' => mt_rand(1, 100) <= 75,
            ];
        }, $mix);
    }

    /** @return list<Member> */
    private function createMembers(array $profiles): array
    {
        $usedNames = [];
        $members = [];

        foreach ($profiles as $i => $profile) {
            do {
                $male = mt_rand(0, 1) === 1;
                $first = $this->pick($male ? self::FIRST_MALE : self::FIRST_FEMALE);
                $last = $this->pick(self::LAST);
            } while (isset($usedNames["{$first} {$last}"]));
            $usedNames["{$first} {$last}"] = true;

            $joined = $profile['status'] === 'none'
                ? $this->today->subDays(mt_rand(0, 10))
                : $this->windowStart->addDays(mt_rand(0, 20));

            Carbon::setTestNow($joined->setTime(mt_rand(7, 19), mt_rand(0, 59)));

            $members[$i] = Member::create([
                'first_name' => $first,
                'middle_name' => mt_rand(1, 100) <= 70 ? $this->pick(self::LAST) : null,
                'last_name' => $last,
                'gender' => $male ? 'male' : 'female',
                'birthdate' => $this->today->subYears(mt_rand(17, 58))->subDays(mt_rand(0, 364))->toDateString(),
                'phone' => $this->phone(),
                'email' => mt_rand(1, 100) <= 35 ? strtolower(str_replace([' ', 'ñ'], ['', 'n'], $first)).'.'.strtolower(str_replace(' ', '', $last)).mt_rand(1, 99).'@gmail.com' : null,
                'address' => mt_rand(1, 10).' '.$this->pick(self::STREETS).', '.$this->pick(self::PUROKS).', Brgy. San Roque',
                'emergency_contact_name' => $this->pick(array_merge(self::FIRST_MALE, self::FIRST_FEMALE)).' '.$last.' ('.$this->pick(['Mother', 'Father', 'Spouse', 'Sibling']).')',
                'emergency_contact_phone' => $this->phone(),
                'notes' => match (mt_rand(1, 12)) {
                    1 => 'Has a knee injury; avoid heavy squats.',
                    2 => 'Goal: lose weight before December.',
                    3 => 'Training for a fun run.',
                    default => null,
                },
                'joined_on' => $joined->toDateString(),
            ]);
        }

        return $members;
    }

    /**
     * Work out each member's payment dates backwards from their target status today,
     * then record all payments in date order so receipt numbers run chronologically.
     */
    private function recordPayments(array $members, array $profiles): void
    {
        $events = [];
        $recentDays = [0, 1, 2, 4]; // a few payments in the last days so the dashboard has fresh activity

        foreach ($profiles as $i => $profile) {
            if ($profile['status'] === 'none') {
                continue;
            }

            $plan = $this->plans[$profile['plan']];
            $duration = $plan->duration_days;

            // Date of the most recent payment, chosen so today's status matches the profile.
            $lastPaid = match ($profile['status']) {
                'active' => match ($plan->type) {
                    PlanType::Monthly => $this->today->subDays($recentDays ? array_shift($recentDays) : mt_rand(0, 21)),
                    PlanType::PerSession => $this->today->subDays(mt_rand(3, 30)),
                    default => $this->today->subDays(mt_rand(5, min(80, $duration - 10))),
                },
                'expiring' => $this->today->subDays($duration - mt_rand(0, 6)),
                'expired', 'inactive' => $this->today->subDays($duration + mt_rand(2, 35)),
                'bundle_used_up' => $this->today->subDays(mt_rand(25, 40)),
            };

            // Today's payment can't be later than the current time.
            if ($lastPaid->isSameDay($this->today) && $this->realNow->hour < 7) {
                $lastPaid = $lastPaid->subDay();
            }

            // Long plans that started before the 3-month window are recorded on their real date.
            $dates = [$lastPaid];
            $previous = $lastPaid;
            while ($profile['renewer'] && $plan->type !== PlanType::Yearly) {
                $previous = $previous->subDays($duration + mt_rand(0, 4));
                if ($previous->lessThan($this->windowStart)) {
                    break;
                }
                $dates[] = $previous;
            }

            foreach ($dates as $date) {
                $events[] = ['member' => $i, 'plan' => $plan, 'date' => $date];
            }
        }

        usort($events, fn ($a, $b) => $a['date'] <=> $b['date']);

        $service = app(MembershipService::class);
        foreach ($events as $event) {
            $member = $members[$event['member']];
            $date = $event['date'];

            if ($member->joined_on->greaterThan($date)) {
                $member->update(['joined_on' => $date->toDateString()]);
            }

            $time = $date->setTime(mt_rand(6, 20), mt_rand(0, 59));
            Carbon::setTestNow($time->greaterThan($this->realNow) ? $this->realNow->subMinutes(mt_rand(5, 60)) : $time);
            $method = $this->pick(['cash', 'cash', 'cash', 'cash', 'gcash', 'gcash', 'maya']);

            $service->recordPayment($member->fresh(), $event['plan'], [
                'amount' => $event['plan']->price,
                'method' => $method,
                'reference_no' => $method === 'cash' ? null : (string) mt_rand(1000000000000, 9999999999999),
                'paid_on' => $date->toDateString(),
            ], mt_rand(1, 100) <= 75 ? $this->staff : $this->admin);
        }
    }

    private function deactivate(array $members, array $profiles): void
    {
        $reasons = ['Moved to Manila for work', 'Injury, on hold for now'];
        $n = 0;

        foreach ($profiles as $i => $profile) {
            if ($profile['status'] === 'inactive') {
                $members[$i]->update([
                    'is_active' => false,
                    'deactivated_at' => $this->today->subDays(mt_rand(3, 15))->setTime(17, 30),
                    'deactivation_reason' => $reasons[$n++ % count($reasons)],
                ]);
            }
        }
    }

    /** Visits on days a member's period covers, more in the morning and evening; a few denied/override visits. */
    private function recordAttendance(array $members, array $profiles): void
    {
        $now = $this->realNow;
        $rows = [];
        $overrides = 0;

        foreach ($profiles as $i => $profile) {
            $member = $members[$i];
            $memberships = Membership::where('member_id', $member->id)->orderBy('start_date')->get();

            if ($memberships->isEmpty()) {
                continue;
            }

            $usedUp = $profile['status'] === 'bundle_used_up';
            $lastVisit = null;

            foreach ($memberships as $membership) {
                $remaining = $membership->sessions_total;
                $day = CarbonImmutable::parse($membership->start_date);
                $end = CarbonImmutable::parse($membership->expiry_date)->min($this->today);

                for (; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
                    $chance = $usedUp ? 0.6 : $profile['frequency'] * ($day->isSunday() ? 0.4 : 1);
                    // Bundles of members meant to be active keep a couple of sessions left.
                    $keepSessions = ! $usedUp && $remaining !== null && $remaining <= 2 && $membership->is($memberships->last());
                    if ($remaining === 0 || $keepSessions || mt_rand(1, 100) > $chance * 100) {
                        continue;
                    }

                    $at = $this->visitTime($day);
                    if ($at->greaterThan($now)) {
                        continue;
                    }

                    if ($remaining !== null) {
                        $remaining--;
                    }

                    $rows[] = $this->attendanceRow($member, $membership, $at, AttendanceStatus::Allowed, $remaining !== null);
                    $lastVisit = $at;
                }

                if ($membership->sessions_total !== null) {
                    $membership->update(['sessions_remaining' => $remaining]);
                }
            }

            // Expired members who still showed up: denied at the desk, or let in once with a note.
            if (in_array($profile['status'], ['expired', 'bundle_used_up'], true) && mt_rand(1, 100) <= 70) {
                $at = $this->visitTime($this->today->subDays(mt_rand(0, 6)));
                if ($at->lessThan($now) && (! $lastVisit || $at->greaterThan($lastVisit))) {
                    $override = $overrides === 0 || mt_rand(1, 100) <= 35;
                    $overrides += $override ? 1 : 0;
                    $rows[] = $this->attendanceRow($member, $memberships->last(), $at, $override ? AttendanceStatus::Manual : AttendanceStatus::Denied, false,
                        $override ? $this->pick(['Will pay on Friday, approved by owner', 'Promised to renew tomorrow', 'Payday on the 15th']) : null);
                }
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Attendance::insert($chunk);
        }
    }

    private function recordWalkins(): void
    {
        $dayPass = $this->plans[PlanType::DayPass->value];
        $now = $this->realNow;

        for ($day = $this->windowStart; $day->lessThanOrEqualTo($this->today); $day = $day->addDay()) {
            $count = $day->isWeekend() ? mt_rand(1, 6) : mt_rand(0, 3);

            for ($n = 0; $n < $count; $n++) {
                $at = $this->visitTime($day);
                if ($at->greaterThan($now)) {
                    continue;
                }

                $named = mt_rand(1, 100) <= 55;
                $method = mt_rand(1, 100) <= 85 ? 'cash' : 'gcash';
                $by = mt_rand(1, 100) <= 80 ? $this->staff : $this->admin;

                Walkin::create([
                    'name' => $named ? $this->pick(array_merge(self::FIRST_MALE, self::FIRST_FEMALE)).' '.$this->pick(self::LAST) : null,
                    'phone' => $named && mt_rand(0, 1) ? $this->phone() : null,
                    'plan_id' => $dayPass->id,
                    'amount' => $dayPass->price,
                    'method' => $method,
                    'reference_no' => $method === 'gcash' ? (string) mt_rand(1000000000000, 9999999999999) : null,
                    'visited_at' => $at,
                    'recorded_by' => $by->id,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }
        }
    }

    private function attendanceRow(Member $member, Membership $membership, CarbonImmutable $at, AttendanceStatus $status, bool $sessionUsed, ?string $note = null): array
    {
        return [
            'member_id' => $member->id,
            'membership_id' => $membership->id,
            'checked_in_at' => $at->toDateTimeString(),
            'status' => $status->value,
            'source' => 'manual',
            'session_used' => $sessionUsed,
            'note' => $note,
            'recorded_by' => (mt_rand(1, 100) <= 80 ? $this->staff : $this->admin)->id,
            'created_at' => $at->toDateTimeString(),
            'updated_at' => $at->toDateTimeString(),
        ];
    }

    /** Busy before work (6–8 AM) and after work (5–8 PM). */
    private function visitTime(CarbonImmutable $day): CarbonImmutable
    {
        $hour = $this->pick([6, 6, 7, 7, 7, 8, 9, 10, 11, 14, 15, 16, 17, 17, 18, 18, 18, 19, 19, 20]);

        return $day->setTime($hour, mt_rand(0, 59));
    }

    private function phone(): string
    {
        return $this->pick(self::PREFIXES).' '.mt_rand(100, 999).' '.mt_rand(1000, 9999);
    }

    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }
}

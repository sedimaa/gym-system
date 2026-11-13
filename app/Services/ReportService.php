<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Walkin;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Numbers for the dashboard and reports. Data for a gym this size is small, so rows are
 * loaded for the period and aggregated in PHP (works the same on MySQL and SQLite).
 * Walk-ins are always kept separate from members.
 */
class ReportService
{
    public function __construct(private readonly MembershipService $memberships) {}

    /** @return list<string> Y-m-d for every day in the range */
    public function days(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return collect(CarbonPeriod::create($from, $to))->map(fn ($d) => $d->toDateString())->all();
    }

    public function income(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $payments = Payment::with(['member', 'plan', 'receiver'])
            ->between($from->toDateString(), $to->toDateString())
            ->orderBy('paid_on')->orderBy('id')->get();

        $walkins = Walkin::with('recorder')
            ->betweenDates($from->toDateString(), $to->toDateString())
            ->orderBy('visited_at')->get();

        $paymentsByDay = $payments->groupBy(fn (Payment $p) => $p->paid_on->toDateString());
        $walkinsByDay = $walkins->groupBy(fn (Walkin $w) => $w->visited_at->toDateString());

        $daily = collect($this->days($from, $to))->map(fn (string $day) => [
            'date' => $day,
            'members' => (float) ($paymentsByDay->get($day)?->sum('amount') ?? 0),
            'walkins' => (float) ($walkinsByDay->get($day)?->sum('amount') ?? 0),
        ])->map(fn ($row) => $row + ['total' => $row['members'] + $row['walkins']]);

        $byPlan = $payments->groupBy(fn (Payment $p) => $p->plan->name)
            ->map(fn (Collection $g, string $name) => ['label' => $name, 'count' => $g->count(), 'amount' => (float) $g->sum('amount')])
            ->sortByDesc('amount')->values();
        if ($walkins->isNotEmpty()) {
            $byPlan->push(['label' => 'Walk-ins (day pass)', 'count' => $walkins->count(), 'amount' => (float) $walkins->sum('amount')]);
        }

        $byMethod = $payments->concat($walkins)
            ->groupBy(fn ($row) => $row->method->label())
            ->map(fn (Collection $g, string $label) => ['label' => $label, 'count' => $g->count(), 'amount' => (float) $g->sum('amount')])
            ->sortByDesc('amount')->values();

        $members = (float) $payments->sum('amount');
        $walkinTotal = (float) $walkins->sum('amount');

        return [
            'totals' => [
                'members' => $members,
                'walkins' => $walkinTotal,
                'total' => $members + $walkinTotal,
                'payments_count' => $payments->count(),
                'walkins_count' => $walkins->count(),
            ],
            'daily' => $daily->values(),
            'byPlan' => $byPlan,
            'byMethod' => $byMethod,
            'payments' => $payments,
            'walkins' => $walkins,
        ];
    }

    public function attendance(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $records = Attendance::with(['member', 'recorder'])
            ->betweenDates($from->toDateString(), $to->toDateString())
            ->orderBy('checked_in_at')->get();
        $walkins = Walkin::betweenDates($from->toDateString(), $to->toDateString())->get(['id', 'visited_at']);

        $visits = $records->filter(fn (Attendance $a) => $a->status->isVisit());
        $visitsByDay = $visits->groupBy(fn (Attendance $a) => $a->checked_in_at->toDateString());
        $deniedByDay = $records->where('status', AttendanceStatus::Denied)->groupBy(fn (Attendance $a) => $a->checked_in_at->toDateString());
        $walkinsByDay = $walkins->groupBy(fn (Walkin $w) => $w->visited_at->toDateString());

        $daily = collect($this->days($from, $to))->map(function (string $day) use ($visitsByDay, $walkinsByDay, $deniedByDay) {
            $members = $visitsByDay->get($day)?->count() ?? 0;
            $walkins = $walkinsByDay->get($day)?->count() ?? 0;

            return ['date' => $day, 'members' => $members, 'walkins' => $walkins, 'total' => $members + $walkins, 'denied' => $deniedByDay->get($day)?->count() ?? 0];
        })->values();

        $byHour = collect(range(5, 22))->mapWithKeys(fn ($h) => [$h => 0])->all();
        foreach ($visits->pluck('checked_in_at')->concat($walkins->pluck('visited_at')) as $time) {
            $hour = (int) $time->format('G');
            $byHour[$hour] = ($byHour[$hour] ?? 0) + 1;
        }
        ksort($byHour);

        $busiest = $daily->sortByDesc('total')->first();
        $dayCount = max(1, $daily->count());

        return [
            'totals' => [
                'members' => $visits->count(),
                'walkins' => $walkins->count(),
                'total' => $visits->count() + $walkins->count(),
                'denied' => $records->where('status', AttendanceStatus::Denied)->count(),
                'overrides' => $records->where('status', AttendanceStatus::Manual)->count(),
                'unique_members' => $visits->pluck('member_id')->unique()->count(),
                'average_per_day' => round(($visits->count() + $walkins->count()) / $dayCount, 1),
                'busiest_day' => $busiest && $busiest['total'] > 0 ? $busiest : null,
            ],
            'daily' => $daily,
            'byHour' => $byHour,
            'records' => $records,
        ];
    }

    public function members(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $newMembers = Member::whereDate('joined_on', '>=', $from->toDateString())
            ->whereDate('joined_on', '<=', $to->toDateString())
            ->orderBy('joined_on')->get();

        $payments = Payment::with(['member', 'plan'])
            ->between($from->toDateString(), $to->toDateString())
            ->orderBy('paid_on')->get();

        $firstPlans = $payments->where('kind', 'new');
        $renewals = $payments->where('kind', 'renewal');

        return [
            'totals' => [
                'new_members' => $newMembers->count(),
                'first_payments' => $firstPlans->count(),
                'renewals' => $renewals->count(),
                'renewal_income' => (float) $renewals->sum('amount'),
            ],
            'statusCounts' => $this->memberships->statusCounts(),
            'newMembers' => $newMembers,
            'firstPlans' => $firstPlans->values(),
            'renewals' => $renewals->values(),
        ];
    }
}

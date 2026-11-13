@php
    use App\Support\Chart;
    $short = fn ($d) => \Carbon\Carbon::parse($d)->format('M j');
@endphp
<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Dashboard</h1>
    </x-slot>

    {{-- Quick actions --}}
    <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
        <a href="{{ route('attendance.index') }}" class="btn-success py-3">✓ Check in member</a>
        <a href="{{ route('payments.create') }}" class="btn-primary py-3">₱ Record payment</a>
        <a href="{{ route('walkins.index') }}" class="btn-warning py-3">+ Walk-in</a>
        <a href="{{ route('members.create') }}" class="btn-secondary py-3">+ Register member</a>
    </div>

    {{-- Today --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card card-body">
            <p class="stat-label">Income today</p>
            <p class="stat-value">@peso($todayIncome['total'])</p>
            <p class="text-xs text-gray-500">Members @peso($todayIncome['members']) · Walk-ins @peso($todayIncome['walkins'])</p>
        </div>
        <div class="card card-body">
            <p class="stat-label">Visits today</p>
            <p class="stat-value">{{ $todayVisits['total'] }}</p>
            <p class="text-xs text-gray-500">{{ $todayVisits['members'] }} members · {{ $todayVisits['walkins'] }} walk-ins{{ $todayVisits['denied'] ? " · {$todayVisits['denied']} denied" : '' }}</p>
        </div>
        <div class="card card-body">
            <p class="stat-label">Income this month</p>
            <p class="stat-value">@peso($monthIncome['total'])</p>
            <p class="text-xs text-gray-500">{{ $monthIncome['payments_count'] }} payments · {{ $monthIncome['walkins_count'] }} walk-ins</p>
        </div>
        <div class="card card-body">
            <p class="stat-label">Members</p>
            <p class="stat-value">{{ $statusCounts['active'] + $statusCounts['expiring'] }} <span class="text-sm font-normal text-gray-500">with a valid plan</span></p>
            <p class="mt-1 flex flex-wrap gap-1.5 text-xs">
                <a href="{{ route('members.index', ['status' => 'active']) }}" class="badge-green">{{ $statusCounts['active'] }} active</a>
                <a href="{{ route('expiry.index') }}" class="badge-amber">{{ $statusCounts['expiring'] }} expiring</a>
                <a href="{{ route('expiry.index', ['tab' => 'expired']) }}" class="badge-red">{{ $statusCounts['expired'] }} expired</a>
            </p>
        </div>
    </div>

    <div class="mb-6 grid gap-6 xl:grid-cols-3">
        <div class="card card-body xl:col-span-2">
            <h2 class="card-title mb-3">Income, last 30 days</h2>
            <div class="h-64"><canvas data-chart="{{ Chart::bar(
                $incomeChart->pluck('date')->map($short)->all(),
                [['label' => 'Members', 'data' => $incomeChart->pluck('members')->all(), 'color' => Chart::COLORS['members']],
                 ['label' => 'Walk-ins', 'data' => $incomeChart->pluck('walkins')->all(), 'color' => Chart::COLORS['walkins']]],
                money: true) }}"></canvas></div>
        </div>
        <div class="card card-body">
            <h2 class="card-title mb-3">Visits, last 14 days</h2>
            <div class="h-64"><canvas data-chart="{{ Chart::bar(
                $attendanceChart->pluck('date')->map($short)->all(),
                [['label' => 'Members', 'data' => $attendanceChart->pluck('members')->all(), 'color' => Chart::COLORS['members']],
                 ['label' => 'Walk-ins', 'data' => $attendanceChart->pluck('walkins')->all(), 'color' => Chart::COLORS['walkins']]]) }}"></canvas></div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-body flex items-center justify-between pb-3">
                <h2 class="card-title">Expiring soon</h2>
                <a href="{{ route('expiry.index') }}" class="link text-sm">See all</a>
            </div>
            <table class="table">
                <tbody>
                    @forelse ($expiring as $member)
                        @php($days = $member->currentMembership->daysLeft())
                        <tr>
                            <td><a href="{{ route('members.show', $member) }}" class="font-medium hover:underline">{{ $member->full_name }}</a><div class="text-xs text-gray-500">{{ $member->currentMembership->plan->name }} · {{ $member->phone }}</div></td>
                            <td class="whitespace-nowrap"><span class="badge-amber">{{ $days > 0 ? $days.' '.Str::plural('day', $days).' left' : ($days === 0 ? 'Today' : abs($days).' '.Str::plural('day', abs($days)).' over') }}</span></td>
                            <td class="text-right"><a href="{{ route('payments.create', ['member' => $member->id, 'plan' => $member->currentMembership->plan_id]) }}" class="btn-primary btn-sm">Renew</a></td>
                        </tr>
                    @empty
                        <tr><td class="py-6 text-center text-gray-500">No memberships expiring soon.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card overflow-hidden">
            <div class="card-body flex items-center justify-between pb-3">
                <h2 class="card-title">Recent payments</h2>
                <a href="{{ route('payments.index') }}" class="link text-sm">See all</a>
            </div>
            <table class="table">
                <tbody>
                    @forelse ($recentPayments as $payment)
                        <tr>
                            <td><a href="{{ route('payments.show', $payment) }}" class="font-medium hover:underline">{{ $payment->member->full_name }}</a><div class="text-xs text-gray-500">{{ $payment->plan->name }} · {{ $payment->kindLabel() }}</div></td>
                            <td class="whitespace-nowrap text-gray-600">{{ $payment->paid_on->format('M j') }}</td>
                            <td class="text-right font-medium tabular-nums">@peso($payment->amount)</td>
                        </tr>
                    @empty
                        <tr><td class="py-6 text-center text-gray-500">No payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

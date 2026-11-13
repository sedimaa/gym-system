@php
    use App\Support\Chart;
    $short = fn ($d) => \Carbon\Carbon::parse($d)->format('M j');
    $query = ['type' => $type, 'from' => $from->toDateString(), 'to' => $to->toDateString()];
@endphp
<x-app-layout>
    <x-slot name="title">Reports</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Reports</h1>
    </x-slot>

    {{-- Report type tabs --}}
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($types as $key => $label)
            <a href="{{ route('reports.index', ['type' => $key, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
               @class(['btn', 'bg-slate-800 text-white' => $type === $key, 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => $type !== $key])>{{ $label }}</a>
        @endforeach
    </div>

    {{-- Period + export --}}
    <div class="card card-body mb-6">
        <form method="GET" class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="flex flex-wrap gap-1.5">
                @foreach ($presets as $key => $label)
                    <button name="preset" value="{{ $key }}" class="btn-secondary btn-sm">{{ $label }}</button>
                @endforeach
            </div>
            <div class="flex items-end gap-2 lg:ml-auto">
                <div><label for="from" class="form-label">From</label><input id="from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-input"></div>
                <div><label for="to" class="form-label">To</label><input id="to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-input"></div>
                <button class="btn-primary">Show</button>
            </div>
        </form>
        @error('to') <p class="form-error">{{ $message }}</p> @enderror

        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
            <p class="text-sm text-gray-600">{{ $types[$type] }} · {{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }}</p>
            <div class="flex gap-2 sm:ml-auto">
                <a href="{{ route('reports.export', ['format' => 'xlsx'] + $query) }}" class="btn-secondary btn-sm">⬇ Excel</a>
                <a href="{{ route('reports.export', ['format' => 'pdf'] + $query) }}" class="btn-secondary btn-sm">⬇ PDF</a>
            </div>
        </div>
    </div>

    @if ($type === 'income')
        @php($t = $report['totals'])
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="card card-body"><p class="stat-label">Total income</p><p class="stat-value">@peso($t['total'])</p></div>
            <div class="card card-body"><p class="stat-label">Members</p><p class="stat-value">@peso($t['members'])</p><p class="text-xs text-gray-500">{{ $t['payments_count'] }} payments</p></div>
            <div class="card card-body"><p class="stat-label">Walk-ins</p><p class="stat-value">@peso($t['walkins'])</p><p class="text-xs text-gray-500">{{ $t['walkins_count'] }} walk-ins</p></div>
        </div>
        <div class="card card-body mb-6">
            <h2 class="card-title mb-3">Income per day</h2>
            <div class="h-72"><canvas data-chart="{{ Chart::bar(
                $report['daily']->pluck('date')->map($short)->all(),
                [['label' => 'Members', 'data' => $report['daily']->pluck('members')->all(), 'color' => Chart::COLORS['members']],
                 ['label' => 'Walk-ins', 'data' => $report['daily']->pluck('walkins')->all(), 'color' => Chart::COLORS['walkins']]],
                money: true) }}"></canvas></div>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach (['byPlan' => 'By plan', 'byMethod' => 'By payment method'] as $key => $title)
                <div class="card overflow-hidden">
                    <div class="card-body pb-3"><h2 class="card-title">{{ $title }}</h2></div>
                    <table class="table">
                        <thead><tr><th>{{ $key === 'byPlan' ? 'Plan' : 'Paid via' }}</th><th class="text-right">Count</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                            @forelse ($report[$key] as $row)
                                <tr><td>{{ $row['label'] }}</td><td class="text-right">{{ $row['count'] }}</td><td class="text-right font-medium tabular-nums">@peso($row['amount'])</td></tr>
                            @empty
                                <tr><td colspan="3" class="py-6 text-center text-gray-500">No income in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>

    @elseif ($type === 'attendance')
        @php($t = $report['totals'])
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card card-body"><p class="stat-label">Total visits</p><p class="stat-value">{{ number_format($t['total']) }}</p><p class="text-xs text-gray-500">{{ $t['average_per_day'] }} per day on average</p></div>
            <div class="card card-body"><p class="stat-label">Member check-ins</p><p class="stat-value">{{ number_format($t['members']) }}</p><p class="text-xs text-gray-500">{{ $t['unique_members'] }} different members · {{ $t['overrides'] }} overrides</p></div>
            <div class="card card-body"><p class="stat-label">Walk-ins</p><p class="stat-value">{{ number_format($t['walkins']) }}</p></div>
            <div class="card card-body"><p class="stat-label">Denied</p><p class="stat-value text-red-600">{{ number_format($t['denied']) }}</p>@if ($t['busiest_day'])<p class="text-xs text-gray-500">Busiest: {{ \Carbon\Carbon::parse($t['busiest_day']['date'])->format('D, M j') }} ({{ $t['busiest_day']['total'] }})</p>@endif</div>
        </div>
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="card card-body lg:col-span-2">
                <h2 class="card-title mb-3">Visits per day</h2>
                <div class="h-72"><canvas data-chart="{{ Chart::bar(
                    $report['daily']->pluck('date')->map($short)->all(),
                    [['label' => 'Members', 'data' => $report['daily']->pluck('members')->all(), 'color' => Chart::COLORS['members']],
                     ['label' => 'Walk-ins', 'data' => $report['daily']->pluck('walkins')->all(), 'color' => Chart::COLORS['walkins']]]) }}"></canvas></div>
            </div>
            <div class="card card-body">
                <h2 class="card-title mb-3">Busiest hours</h2>
                <div class="h-72"><canvas data-chart="{{ Chart::bar(
                    collect(array_keys($report['byHour']))->map(fn ($h) => \Carbon\Carbon::createFromTime($h)->format('ga'))->all(),
                    [['label' => 'Visits', 'data' => array_values($report['byHour']), 'color' => Chart::COLORS['accent']]], stacked: false) }}"></canvas></div>
            </div>
        </div>

    @else
        @php($t = $report['totals'])
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card card-body"><p class="stat-label">New members</p><p class="stat-value">{{ $t['new_members'] }}</p><p class="text-xs text-gray-500">Registered in this period</p></div>
            <div class="card card-body"><p class="stat-label">First plans bought</p><p class="stat-value">{{ $t['first_payments'] }}</p></div>
            <div class="card card-body"><p class="stat-label">Renewals</p><p class="stat-value">{{ $t['renewals'] }}</p><p class="text-xs text-gray-500">@peso($t['renewal_income'])</p></div>
            <div class="card card-body">
                <p class="stat-label">Members right now</p>
                <p class="mt-2 flex flex-wrap gap-1.5 text-xs">
                    <span class="badge-green">{{ $report['statusCounts']['active'] }} active</span>
                    <span class="badge-amber">{{ $report['statusCounts']['expiring'] }} expiring</span>
                    <span class="badge-red">{{ $report['statusCounts']['expired'] }} expired</span>
                </p>
            </div>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card overflow-hidden">
                <div class="card-body pb-3"><h2 class="card-title">New members</h2></div>
                <table class="table">
                    <thead><tr><th>Joined</th><th>Member</th><th>Phone</th></tr></thead>
                    <tbody>
                        @forelse ($report['newMembers'] as $m)
                            <tr><td class="whitespace-nowrap">{{ $m->joined_on->format('M j, Y') }}</td><td><a href="{{ route('members.show', $m) }}" class="hover:underline">{{ $m->full_name }}</a> <span class="font-mono text-xs text-gray-500">{{ $m->member_code }}</span></td><td>{{ $m->phone ?? '—' }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-gray-500">No new members in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card overflow-hidden">
                <div class="card-body pb-3"><h2 class="card-title">Renewals</h2></div>
                <table class="table">
                    <thead><tr><th>Date</th><th>Member</th><th>Plan</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        @forelse ($report['renewals'] as $p)
                            <tr><td class="whitespace-nowrap">{{ $p->paid_on->format('M j, Y') }}</td><td><a href="{{ route('members.show', $p->member) }}" class="hover:underline">{{ $p->member->full_name }}</a></td><td>{{ $p->plan->name }}</td><td class="text-right tabular-nums">@peso($p->amount)</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-gray-500">No renewals in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>

@php($money = fn ($v) => \App\Support\Money::peso($v))
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28pt 30pt; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111; }
        h1 { font-size: 14pt; margin: 0; }
        h2 { font-size: 10.5pt; margin: 16pt 0 6pt; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f1f5f9; font-size: 7.5pt; text-transform: uppercase; }
        th, td { padding: 3pt 5pt; border-bottom: 0.5pt solid #ddd; }
        .r { text-align: right; }
        .stats td { border: 0.5pt solid #ccc; width: 25%; padding: 6pt; }
        .stats .v { font-size: 12pt; font-weight: bold; }
        tr.total td { font-weight: bold; border-top: 1pt solid #333; }
    </style>
</head>
<body>
    <h1>{{ $gymName }}: {{ $types[$type] }} report</h1>
    <p class="muted">{{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }} · Generated {{ now()->format('M j, Y g:i A') }}</p>
    @php($t = $report['totals'])

    @if ($type === 'income')
        <table class="stats"><tr>
            <td>Total income<div class="v">{{ $money($t['total']) }}</div></td>
            <td>Members<div class="v">{{ $money($t['members']) }}</div>{{ $t['payments_count'] }} payments</td>
            <td>Walk-ins<div class="v">{{ $money($t['walkins']) }}</div>{{ $t['walkins_count'] }} walk-ins</td>
        </tr></table>

        <h2>By plan</h2>
        <table><tr><th>Plan</th><th class="r">Count</th><th class="r">Amount</th></tr>
            @foreach ($report['byPlan'] as $r)<tr><td>{{ $r['label'] }}</td><td class="r">{{ $r['count'] }}</td><td class="r">{{ $money($r['amount']) }}</td></tr>@endforeach
        </table>
        <h2>By payment method</h2>
        <table><tr><th>Paid via</th><th class="r">Count</th><th class="r">Amount</th></tr>
            @foreach ($report['byMethod'] as $r)<tr><td>{{ $r['label'] }}</td><td class="r">{{ $r['count'] }}</td><td class="r">{{ $money($r['amount']) }}</td></tr>@endforeach
        </table>
        <h2>Daily income</h2>
        <table><tr><th>Date</th><th class="r">Members</th><th class="r">Walk-ins</th><th class="r">Total</th></tr>
            @foreach ($report['daily'] as $r)
                @if ($r['total'] > 0)<tr><td>{{ \Carbon\Carbon::parse($r['date'])->format('D, M j, Y') }}</td><td class="r">{{ $money($r['members']) }}</td><td class="r">{{ $money($r['walkins']) }}</td><td class="r">{{ $money($r['total']) }}</td></tr>@endif
            @endforeach
            <tr class="total"><td>Total</td><td class="r">{{ $money($t['members']) }}</td><td class="r">{{ $money($t['walkins']) }}</td><td class="r">{{ $money($t['total']) }}</td></tr>
        </table>

    @elseif ($type === 'attendance')
        <table class="stats"><tr>
            <td>Total visits<div class="v">{{ $t['total'] }}</div>{{ $t['average_per_day'] }} per day</td>
            <td>Member check-ins<div class="v">{{ $t['members'] }}</div>{{ $t['unique_members'] }} members · {{ $t['overrides'] }} overrides</td>
            <td>Walk-ins<div class="v">{{ $t['walkins'] }}</div></td>
            <td>Denied<div class="v">{{ $t['denied'] }}</div></td>
        </tr></table>
        <h2>Daily attendance</h2>
        <table><tr><th>Date</th><th class="r">Members</th><th class="r">Walk-ins</th><th class="r">Total</th><th class="r">Denied</th></tr>
            @foreach ($report['daily'] as $r)
                <tr><td>{{ \Carbon\Carbon::parse($r['date'])->format('D, M j, Y') }}</td><td class="r">{{ $r['members'] }}</td><td class="r">{{ $r['walkins'] }}</td><td class="r">{{ $r['total'] }}</td><td class="r">{{ $r['denied'] }}</td></tr>
            @endforeach
            <tr class="total"><td>Total</td><td class="r">{{ $t['members'] }}</td><td class="r">{{ $t['walkins'] }}</td><td class="r">{{ $t['total'] }}</td><td class="r">{{ $t['denied'] }}</td></tr>
        </table>

    @else
        <table class="stats"><tr>
            <td>New members<div class="v">{{ $t['new_members'] }}</div></td>
            <td>First plans bought<div class="v">{{ $t['first_payments'] }}</div></td>
            <td>Renewals<div class="v">{{ $t['renewals'] }}</div>{{ $money($t['renewal_income']) }}</td>
            <td>Members now<div class="v">{{ $report['statusCounts']['active'] + $report['statusCounts']['expiring'] }}</div>{{ $report['statusCounts']['expired'] }} expired</td>
        </tr></table>
        <h2>New members</h2>
        <table><tr><th>Joined</th><th>Code</th><th>Name</th><th>Phone</th></tr>
            @forelse ($report['newMembers'] as $m)<tr><td>{{ $m->joined_on->format('M j, Y') }}</td><td>{{ $m->member_code }}</td><td>{{ $m->full_name }}</td><td>{{ $m->phone }}</td></tr>
            @empty <tr><td colspan="4" class="muted">None</td></tr> @endforelse
        </table>
        <h2>Renewals</h2>
        <table><tr><th>Date</th><th>Receipt</th><th>Member</th><th>Plan</th><th class="r">Amount</th></tr>
            @forelse ($report['renewals'] as $p)<tr><td>{{ $p->paid_on->format('M j, Y') }}</td><td>{{ $p->receipt_no }}</td><td>{{ $p->member->full_name }}</td><td>{{ $p->plan->name }}</td><td class="r">{{ $money($p->amount) }}</td></tr>
            @empty <tr><td colspan="5" class="muted">None</td></tr> @endforelse
        </table>
    @endif
</body>
</html>

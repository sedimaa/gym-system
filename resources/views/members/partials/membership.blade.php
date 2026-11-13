@php($current = $member->currentMembership)
<div class="card card-body">
    <h3 class="card-title mb-4">Membership</h3>

    @if (! $current)
        <p class="text-sm text-gray-600">No plan yet. Record the first payment to start a membership.</p>
    @else
        @php($days = $current->daysLeft())
        <div class="grid gap-4 sm:grid-cols-4">
            <div><p class="stat-label">Plan</p><p class="mt-1 font-semibold">{{ $current->plan->name }}</p></div>
            <div><p class="stat-label">Started</p><p class="mt-1 font-semibold">{{ $current->start_date->format('M j, Y') }}</p></div>
            <div>
                <p class="stat-label">Expires</p>
                <p class="mt-1 font-semibold">{{ $current->expiry_date->format('M j, Y') }}</p>
                <p @class(['text-xs', 'text-red-600' => $days < 0, 'text-amber-600' => $days >= 0 && $status === App\Enums\MembershipStatus::Expiring, 'text-gray-500' => $status === App\Enums\MembershipStatus::Active])>
                    @if ($days > 0) {{ $days }} {{ Str::plural('day', $days) }} left
                    @elseif ($days === 0) Expires today
                    @else Expired {{ abs($days) }} {{ Str::plural('day', abs($days)) }} ago
                    @endif
                </p>
            </div>
            @if ($current->sessions_total !== null)
                <div><p class="stat-label">Sessions left</p><p class="mt-1 font-semibold">{{ $current->sessions_remaining }} <span class="text-sm font-normal text-gray-500">of {{ $current->sessions_total }}</span></p></div>
            @endif
        </div>
        @if ($current->start_date->isFuture())
            <p class="mt-3 rounded-md bg-blue-50 px-3 py-2 text-sm text-blue-800">This period starts on {{ $current->start_date->format('M j, Y') }} (paid in advance).</p>
        @endif
    @endif

    @if ($member->memberships->count() > 1)
        <details class="mt-5">
            <summary class="cursor-pointer text-sm font-medium text-slate-700">Membership history ({{ $member->memberships->count() }} periods)</summary>
            <div class="mt-3 overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Plan</th><th>Start</th><th>Expiry</th><th>Sessions</th><th>Receipt</th></tr></thead>
                    <tbody>
                        @foreach ($member->memberships as $period)
                            <tr>
                                <td>{{ $period->plan->name }}</td>
                                <td class="whitespace-nowrap">{{ $period->start_date->format('M j, Y') }}</td>
                                <td class="whitespace-nowrap">{{ $period->expiry_date->format('M j, Y') }}</td>
                                <td>{{ $period->sessions_total !== null ? $period->sessions_remaining.' / '.$period->sessions_total : '—' }}</td>
                                <td>@if ($period->payment)<a class="link font-mono text-xs" href="{{ route('payments.show', $period->payment) }}">{{ $period->payment->receipt_no }}</a>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</div>

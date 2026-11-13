<x-app-layout>
    <x-slot name="title">Expiry tracking</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Expiry tracking</h1>
    </x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a href="{{ route('expiry.index') }}" @class(['btn', 'bg-amber-500 text-white' => $tab === 'expiring', 'border border-gray-300 bg-white text-gray-700' => $tab !== 'expiring'])>
            Expiring soon <span class="ml-1 rounded-full bg-white/20 px-2 text-xs">{{ $counts['expiring'] }}</span>
        </a>
        <a href="{{ route('expiry.index', ['tab' => 'expired']) }}" @class(['btn', 'bg-red-600 text-white' => $tab === 'expired', 'border border-gray-300 bg-white text-gray-700' => $tab !== 'expired'])>
            Expired <span class="ml-1 rounded-full bg-white/20 px-2 text-xs">{{ $counts['expired'] }}</span>
        </a>
        <p class="ml-auto text-xs text-gray-500">
            "Expiring soon" = within {{ $soonDays }} days{{ $graceDays ? " (including the {$graceDays}-day grace period)" : '' }}. Per-session bundles with no sessions left count as expired.
        </p>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr><th>Member</th><th>Phone</th><th>Plan</th><th>Expiry</th><th></th><th class="text-right"></th></tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        @php($m = $member->currentMembership)
                        @php($days = $m->daysLeft())
                        <tr>
                            <td>
                                <a href="{{ route('members.show', $member) }}" class="font-medium hover:underline">{{ $member->full_name }}</a>
                                <div class="font-mono text-xs text-gray-500">{{ $member->member_code }}</div>
                            </td>
                            <td class="whitespace-nowrap">{{ $member->phone ?? '—' }}</td>
                            <td>
                                {{ $m->plan->name }}
                                @if ($m->sessions_total !== null)
                                    <div class="text-xs text-gray-500">{{ $m->sessions_remaining }} of {{ $m->sessions_total }} sessions left</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">{{ $m->expiry_date->format('D, M j, Y') }}</td>
                            <td class="whitespace-nowrap">
                                @if ($days > 0)
                                    <span class="badge-amber">{{ $days }} {{ Str::plural('day', $days) }} left</span>
                                @elseif ($days === 0)
                                    <span class="badge-amber">Expires today</span>
                                @else
                                    <span class="badge-red">{{ abs($days) }} {{ Str::plural('day', abs($days)) }} ago</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('payments.create', ['member' => $member->id, 'plan' => $m->plan_id]) }}" class="btn-primary btn-sm">Renew</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-gray-500">{{ $tab === 'expired' ? 'No expired members.' : 'No memberships expiring soon.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="title">Attendance</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Attendance</h1>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-5">
        {{-- Check-in desk --}}
        <div class="space-y-4 xl:col-span-2">
            <div class="card card-body">
                <h2 class="card-title">Check in a member</h2>
                <form method="GET" action="{{ route('attendance.index') }}" class="mt-3 flex gap-2">
                    <input name="q" type="search" value="{{ $q }}" class="form-input mt-0 text-base" placeholder="Member code (GYM-0001), name or phone" autofocus autocomplete="off">
                    <button class="btn-primary">Find</button>
                </form>

                @if ($q !== '' && $matches->count() > 1)
                    <ul class="mt-3 divide-y divide-gray-100 rounded-md border border-gray-200">
                        @foreach ($matches as $match)
                            <li>
                                <a href="{{ route('attendance.index', ['member' => $match->id]) }}" class="flex items-center justify-between gap-3 px-3 py-2 hover:bg-gray-50">
                                    <span><span class="font-medium">{{ $match->last_name }}, {{ $match->first_name }}</span> <span class="font-mono text-xs text-gray-500">{{ $match->member_code }}</span></span>
                                    <x-membership-status :status="app(App\Services\MembershipService::class)->status($match)" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @elseif ($q !== '' && $matches->isEmpty())
                    <p class="mt-3 text-sm text-gray-500">No member found. For a non-member, record a <a href="{{ route('walkins.index') }}" class="link">walk-in</a>.</p>
                @endif
            </div>

            @if ($selected)
                @php($current = $selected->currentMembership)
                @php($ok = $selectedStatus->allowsCheckIn())
                <div @class(['card card-body border-2', 'border-green-500' => $ok && ! $alreadyIn, 'border-red-500' => ! $ok, 'border-gray-200' => $ok && $alreadyIn])>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <a href="{{ route('members.show', $selected) }}" class="text-lg font-semibold hover:underline">{{ $selected->full_name }}</a>
                            <p class="font-mono text-xs text-gray-500">{{ $selected->member_code }}</p>
                        </div>
                        <x-membership-status :status="$selectedStatus" class="text-sm" />
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        <div><dt class="text-gray-500">Plan</dt><dd class="font-medium">{{ $current?->plan->name ?? 'None' }}</dd></div>
                        <div><dt class="text-gray-500">Expires</dt><dd class="font-medium">{{ $current?->expiry_date->format('M j, Y') ?? '—' }}</dd></div>
                        @if ($current?->sessions_total !== null)
                            <div><dt class="text-gray-500">Sessions left</dt><dd class="font-medium">{{ $current->sessions_remaining }} of {{ $current->sessions_total }}</dd></div>
                        @endif
                    </dl>

                    @if ($alreadyIn)
                        <p class="mt-4 rounded-md bg-blue-50 px-3 py-2 text-sm text-blue-800">Already checked in today at {{ $alreadyIn->checked_in_at->format('g:i A') }}.</p>
                    @elseif ($ok)
                        <form method="POST" action="{{ route('attendance.store') }}" class="mt-4">
                            @csrf
                            <input type="hidden" name="member_id" value="{{ $selected->id }}">
                            <input type="hidden" name="action" value="allow">
                            <button class="btn-success w-full py-3 text-base">
                                ✓ Check in{{ $current?->sessions_total !== null ? ' (uses 1 session)' : '' }}
                            </button>
                        </form>
                        @if ($selectedStatus === App\Enums\MembershipStatus::Expiring)
                            <p class="mt-2 text-xs text-amber-700">Expiring soon: remind the member to renew.
                                <a href="{{ route('payments.create', ['member' => $selected->id, 'plan' => $current?->plan_id]) }}" class="link">Renew now</a></p>
                        @endif
                    @else
                        <div class="mt-4 rounded-md bg-red-50 px-3 py-3 text-sm text-red-800">
                            <p class="font-semibold">⚠ Not allowed to check in</p>
                            <p>
                                @switch($selectedStatus)
                                    @case(App\Enums\MembershipStatus::Inactive) This member is deactivated. @break
                                    @case(App\Enums\MembershipStatus::None) This member has no plan yet. @break
                                    @default
                                        @if ($current?->sessions_remaining === 0) All sessions in this bundle have been used.
                                        @else Membership expired on {{ $current?->expiry_date->format('M j, Y') }}.
                                        @endif
                                @endswitch
                            </p>
                            @if ($selected->is_active)
                                <a href="{{ route('payments.create', ['member' => $selected->id, 'plan' => $current?->plan_id]) }}" class="mt-2 inline-block font-medium underline">Renew / record payment</a>
                            @endif
                        </div>

                        <div class="mt-4 grid gap-3" x-data="{ override: {{ $errors->has('note') ? 'true' : 'false' }} }">
                            <form method="POST" action="{{ route('attendance.store') }}">
                                @csrf
                                <input type="hidden" name="member_id" value="{{ $selected->id }}">
                                <input type="hidden" name="action" value="deny">
                                <button class="btn-danger w-full">Deny entry</button>
                            </form>
                            <button type="button" class="btn-secondary w-full" x-show="! override" @click="override = true">Override (let in with a note)</button>
                            <form method="POST" action="{{ route('attendance.store') }}" x-show="override" x-cloak class="space-y-2">
                                @csrf
                                <input type="hidden" name="member_id" value="{{ $selected->id }}">
                                <input type="hidden" name="action" value="override">
                                <label for="note" class="form-label">Reason (required)</label>
                                <input id="note" name="note" class="form-input" maxlength="255" placeholder='e.g. "Will pay tomorrow, approved by owner"' value="{{ old('note') }}">
                                @error('note') <p class="form-error">{{ $message }}</p> @enderror
                                <button class="btn-warning w-full">Let in (override)</button>
                            </form>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Day log --}}
        <div class="xl:col-span-3">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <form method="GET" class="flex items-end gap-2">
                    <div>
                        <label for="date" class="form-label">Date</label>
                        <input id="date" name="date" type="date" value="{{ $date }}" max="{{ today()->toDateString() }}" class="form-input" onchange="this.form.submit()">
                    </div>
                    @if ($date !== today()->toDateString())
                        <a href="{{ route('attendance.index') }}" class="pb-2 text-sm text-gray-600 hover:text-gray-900">Today</a>
                    @endif
                </form>
                @can('addRecord', App\Models\Attendance::class)
                    <a href="{{ route('attendance.create') }}" class="btn-secondary btn-sm">+ Add missed record</a>
                @endcan
            </div>

            <div class="mb-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="card px-4 py-3"><p class="stat-label">Total</p><p class="text-xl font-semibold">{{ $totals['total'] }}</p></div>
                <div class="card px-4 py-3"><p class="stat-label">Members</p><p class="text-xl font-semibold">{{ $totals['members'] }}</p></div>
                <div class="card px-4 py-3"><p class="stat-label">Walk-ins</p><p class="text-xl font-semibold">{{ $totals['walkins'] }}</p></div>
                <div class="card px-4 py-3"><p class="stat-label">Denied</p><p class="text-xl font-semibold text-red-600">{{ $totals['denied'] }}</p></div>
            </div>

            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Time</th><th>Who</th><th>Status</th><th>Note</th><th>By</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($log as $row)
                                @php($r = $row['record'])
                                <tr>
                                    <td class="whitespace-nowrap">{{ $row['at']->format('g:i A') }}</td>
                                    @if ($row['type'] === 'member')
                                        <td>
                                            <a href="{{ route('members.show', $r->member) }}" class="font-medium hover:underline">{{ $r->member->full_name }}</a>
                                            <span class="font-mono text-xs text-gray-500">{{ $r->member->member_code }}</span>
                                        </td>
                                        <td><span class="{{ $r->status->badgeClass() }}">{{ $r->status->label() }}</span>@if ($r->session_used) <span class="text-xs text-gray-500">· 1 session</span>@endif</td>
                                        <td class="max-w-xs text-xs text-gray-600">{{ $r->note }}</td>
                                        <td class="text-gray-600">{{ $r->recorder->name }}</td>
                                        <td class="whitespace-nowrap text-right">
                                            @can('update', $r) <a href="{{ route('attendance.edit', $r) }}" class="link text-xs">Correct</a> @endcan
                                        </td>
                                    @else
                                        <td>{{ $r->displayName() }} <span class="badge-blue ml-1">Walk-in</span></td>
                                        <td><span class="badge-green">Day pass</span></td>
                                        <td class="text-xs text-gray-600">@peso($r->amount)</td>
                                        <td class="text-gray-600">{{ $r->recorder->name }}</td>
                                        <td></td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-8 text-center text-gray-500">No check-ins on this day yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

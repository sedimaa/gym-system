<x-app-layout>
    <x-slot name="title">Record payment</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Record payment</h1>
    </x-slot>

    @if (! $member)
        {{-- Step 1: pick the member --}}
        <div class="card card-body max-w-2xl">
            <h2 class="card-title">Who is paying?</h2>
            <form method="GET" action="{{ route('payments.create') }}" class="mt-3 flex gap-2">
                <input name="member_q" type="search" value="{{ request('member_q') }}" class="form-input mt-0" placeholder="Search name, member code or phone" autofocus>
                <button class="btn-secondary">Search</button>
            </form>

            @if (request()->filled('member_q'))
                @php($matches = App\Models\Member::active()->search(request('member_q'))->orderBy('last_name')->limit(15)->get())
                <ul class="mt-4 divide-y divide-gray-100 rounded-md border border-gray-200">
                    @forelse ($matches as $match)
                        <li>
                            <a href="{{ route('payments.create', ['member' => $match->id]) }}" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50">
                                <span><span class="font-medium">{{ $match->last_name }}, {{ $match->first_name }}</span> <span class="font-mono text-xs text-gray-500">{{ $match->member_code }}</span></span>
                                <span class="text-sm text-slate-600">Select →</span>
                            </a>
                        </li>
                    @empty
                        <li class="px-4 py-3 text-sm text-gray-500">No active member found. <a href="{{ route('members.create') }}" class="link">Register a new member</a></li>
                    @endforelse
                </ul>
            @endif
        </div>
    @else
        <form method="POST" action="{{ route('payments.store') }}"
              x-data="{
                plans: @js($plans->mapWithKeys(fn ($p) => [$p->id => ['price' => $p->price, 'name' => $p->name]])),
                previews: @js($previews),
                planId: @js((string) old('plan_id', $selectedPlan?->id ?? '')),
                amount: @js((string) old('amount', $selectedPlan?->price ?? '')),
                method: @js(old('method', 'cash')),
                pick(id) { this.planId = String(id); this.amount = this.plans[id].price; },
              }">
            @csrf
            <input type="hidden" name="member_id" value="{{ $member->id }}">

            <div class="grid gap-6 xl:grid-cols-3">
                <div class="space-y-6 xl:col-span-2">
                    {{-- Member --}}
                    <div class="card card-body flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="stat-label">Member</p>
                            <p class="text-base font-semibold">{{ $member->full_name }} <span class="font-mono text-xs font-normal text-gray-500">{{ $member->member_code }}</span></p>
                            <p class="text-sm text-gray-600">
                                @if ($member->currentMembership)
                                    Current: {{ $member->currentMembership->plan->name }}, expires {{ $member->currentMembership->expiry_date->format('M j, Y') }}
                                @else
                                    No plan yet (first payment)
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-membership-status :status="$memberStatus" />
                            <a href="{{ route('payments.create') }}" class="text-sm text-gray-600 hover:text-gray-900">Change</a>
                        </div>
                    </div>

                    {{-- Plan --}}
                    <div class="card card-body">
                        <h2 class="card-title mb-3">Plan</h2>
                        @error('plan_id') <p class="form-error mb-2">{{ $message }}</p> @enderror
                        <div class="grid gap-3 sm:grid-cols-2">
                            @forelse ($plans as $plan)
                                <label class="flex cursor-pointer flex-col rounded-md border p-4 transition"
                                       :class="planId === '{{ $plan->id }}' ? 'border-slate-700 ring-2 ring-slate-700' : 'border-gray-200 hover:border-gray-300'">
                                    <input type="radio" name="plan_id" value="{{ $plan->id }}" class="sr-only" x-model="planId" @change="pick({{ $plan->id }})">
                                    <span class="flex items-start justify-between gap-2">
                                        <span class="font-medium">{{ $plan->name }}</span>
                                        <span class="font-semibold tabular-nums">@peso($plan->price)</span>
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        {{ $plan->type->label() }} · {{ $plan->durationLabel() }}@if ($plan->session_count) · {{ $plan->session_count }} sessions @endif
                                    </span>
                                    <span class="mt-2 text-xs text-gray-700">
                                        {{ $previews[$plan->id]['start'] }} → {{ $previews[$plan->id]['expiry'] }}
                                    </span>
                                </label>
                            @empty
                                <p class="text-sm text-gray-500">No plans are available for sale. The admin can add plans under Plans &amp; prices.</p>
                            @endforelse
                        </div>
                        <p class="form-help mt-3">
                            Renewing the same plan
                            {{ $renewalMode === 'from_old_expiry' ? 'starts the day after the current expiry (if it hasn\'t passed yet).' : 'starts on the payment date.' }}
                            Changing to a different plan starts on the payment date.
                        </p>
                    </div>
                </div>

                {{-- Payment --}}
                <div class="card card-body h-fit space-y-4">
                    <h2 class="card-title">Payment</h2>

                    <div>
                        <label for="amount" class="form-label">Amount (₱)</label>
                        <input id="amount" name="amount" type="number" step="0.01" min="0" class="form-input text-lg font-semibold" x-model="amount" required>
                        <p class="form-help" x-show="planId && Number(amount) !== Number(plans[planId]?.price)" x-cloak>Differs from the plan price (discount or promo). It will be on the receipt and in the activity log.</p>
                        @error('amount') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="method" class="form-label">Paid via</label>
                        <select id="method" name="method" class="form-select" x-model="method">
                            @foreach ($methods as $m)
                                <option value="{{ $m->value }}">{{ $m->label() }}</option>
                            @endforeach
                        </select>
                        @error('method') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div x-show="method !== 'cash'" x-cloak>
                        <label for="reference_no" class="form-label">Reference no.</label>
                        <input id="reference_no" name="reference_no" class="form-input" value="{{ old('reference_no') }}" maxlength="60">
                        @error('reference_no') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    @can('backdate-payments')
                        <div>
                            <label for="paid_on" class="form-label">Payment date</label>
                            <input id="paid_on" name="paid_on" type="date" class="form-input" value="{{ old('paid_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}">
                            <p class="form-help">Admin only: change this when encoding an older payment.</p>
                            @error('paid_on') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <p class="text-sm text-gray-600">Payment date: <span class="font-medium">{{ today()->format('M j, Y') }}</span></p>
                    @endcan

                    <div>
                        <label for="notes" class="form-label">Notes <span class="font-normal text-gray-400">(optional)</span></label>
                        <input id="notes" name="notes" class="form-input" value="{{ old('notes') }}" maxlength="255">
                    </div>

                    <button type="submit" class="btn-success w-full py-3 text-base" :disabled="! planId">
                        Save payment <span x-show="amount" x-text="'· ₱' + Number(amount).toLocaleString('en-PH', {minimumFractionDigits: 2})"></span>
                    </button>
                </div>
            </div>
        </form>
    @endif
</x-app-layout>

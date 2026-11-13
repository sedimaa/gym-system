<x-app-layout>
    <x-slot name="title">Walk-ins</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Walk-ins</h1>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Record --}}
        <div class="card card-body h-fit">
            <h2 class="card-title">Record a walk-in</h2>

            @if (! $dayPass)
                <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    There is no active day-pass plan yet.
                    @can('manage-plans') <a href="{{ route('plans.create') }}" class="link">Add one under Plans &amp; prices.</a> @else Ask the admin to add one. @endcan
                </p>
            @else
                <form method="POST" action="{{ route('walkins.store') }}" class="mt-4 space-y-4" x-data="{ method: @js(old('method', 'cash')) }">
                    @csrf
                    @error('plan') <p class="form-error">{{ $message }}</p> @enderror

                    <div>
                        <label for="name" class="form-label">Guest name <span class="font-normal text-gray-400">(optional)</span></label>
                        <input id="name" name="name" class="form-input" value="{{ old('name') }}" maxlength="100" autofocus>
                        @error('name') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="phone" class="form-label">Mobile <span class="font-normal text-gray-400">(optional)</span></label>
                        <input id="phone" name="phone" type="tel" class="form-input" value="{{ old('phone') }}" maxlength="20">
                        @error('phone') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="amount" class="form-label">Amount (₱) · {{ $dayPass->name }}</label>
                        @if ($editable)
                            <input id="amount" name="amount" type="number" step="0.01" min="0" class="form-input text-lg font-semibold" value="{{ old('amount', $dayPass->price) }}" required>
                        @else
                            <input id="amount" type="text" class="form-input bg-gray-50 text-lg font-semibold" value="@peso($dayPass->price)" disabled>
                            <p class="form-help">Fixed day-pass price. The admin can allow changes in Settings.</p>
                        @endif
                        @error('amount') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="method" class="form-label">Paid via</label>
                        <select id="method" name="method" class="form-select" x-model="method">
                            @foreach ($methods as $m)
                                <option value="{{ $m->value }}">{{ $m->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="method !== 'cash'" x-cloak>
                        <label for="reference_no" class="form-label">Reference no.</label>
                        <input id="reference_no" name="reference_no" class="form-input" value="{{ old('reference_no') }}" maxlength="60">
                        @error('reference_no') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <p class="text-xs text-gray-500">Date and time are recorded automatically.</p>
                    <button type="submit" class="btn-success w-full py-3">Save walk-in</button>
                </form>
            @endif
        </div>

        {{-- List --}}
        <div class="xl:col-span-2">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <form method="GET" class="flex items-end gap-2">
                    <div>
                        <label for="date" class="form-label">Date</label>
                        <input id="date" name="date" type="date" value="{{ $date }}" max="{{ today()->toDateString() }}" class="form-input" onchange="this.form.submit()">
                    </div>
                    @if ($date !== today()->toDateString())
                        <a href="{{ route('walkins.index') }}" class="pb-2 text-sm text-gray-600 hover:text-gray-900">Today</a>
                    @endif
                </form>
                <div class="text-right">
                    <p class="stat-label">{{ $walkins->count() }} {{ Str::plural('walk-in', $walkins->count()) }}</p>
                    <p class="text-xl font-semibold">@peso($total)</p>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Time</th><th>Guest</th><th class="text-right">Amount</th><th>Paid via</th><th>Recorded by</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($walkins as $walkin)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $walkin->visited_at->format('g:i A') }}</td>
                                    <td>{{ $walkin->displayName() }} @if ($walkin->phone)<span class="text-xs text-gray-500">· {{ $walkin->phone }}</span>@endif</td>
                                    <td class="text-right font-medium tabular-nums">@peso($walkin->amount)</td>
                                    <td>{{ $walkin->method->label() }}@if ($walkin->reference_no) <span class="text-xs text-gray-500">· {{ $walkin->reference_no }}</span>@endif</td>
                                    <td class="text-gray-600">{{ $walkin->recorder->name }}</td>
                                    <td class="text-right">
                                        @can('delete', $walkin)
                                            <form method="POST" action="{{ route('walkins.destroy', $walkin) }}" onsubmit="return confirm('Delete this walk-in?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="link-danger text-xs">Delete</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-8 text-center text-gray-500">No walk-ins on this day.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

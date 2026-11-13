<x-app-layout>
    <x-slot name="title">Activity log</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Activity log</h1>
    </x-slot>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label for="log" class="form-label">Area</label>
            <select id="log" name="log" class="form-select">
                <option value="">All</option>
                @foreach ($logs as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['log'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="user" class="form-label">Done by</label>
            <select id="user" name="user" class="form-select">
                <option value="">Anyone</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected((int) ($filters['user'] ?? 0) === $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="date" class="form-label">Date</label><input id="date" type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="form-input"></div>
        <button class="btn-secondary">Filter</button>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>When</th><th>Who</th><th>Area</th><th>What</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse ($activities as $a)
                        @php
                            $new = collect($a->properties->get('attributes', []));
                            $old = collect($a->properties->get('old', []));
                            $label = match (true) {
                                $a->subject instanceof App\Models\Member => $a->subject->full_name.' ('.$a->subject->member_code.')',
                                $a->subject instanceof App\Models\Plan => $a->subject->name,
                                $a->subject instanceof App\Models\User => $a->subject->name,
                                $a->subject instanceof App\Models\Payment => $a->subject->receipt_no,
                                default => $a->properties->get('member') ?? $a->properties->get('file'),
                            };
                        @endphp
                        <tr class="align-top">
                            <td class="whitespace-nowrap">{{ $a->created_at->format('M j, Y g:i A') }}</td>
                            <td class="whitespace-nowrap">{{ $a->causer?->name ?? 'System' }}</td>
                            <td><span class="badge-slate">{{ $logs[$a->log_name] ?? $a->log_name }}</span></td>
                            <td>
                                <span class="font-medium">{{ ucfirst($a->event ?? '') }}</span>
                                @if ($label) <span class="text-gray-600">· {{ $label }}</span> @endif
                                @if (! in_array($a->description, ['created', 'updated', 'deleted'])) <div class="text-xs text-gray-600">{{ $a->description }}</div> @endif
                            </td>
                            <td class="text-xs text-gray-600">
                                @foreach ($new as $key => $value)
                                    @continue(is_array($value))
                                    <div>
                                        <span class="text-gray-400">{{ str_replace('_', ' ', $key) }}:</span>
                                        @if ($old->has($key) && ! is_array($old[$key])) <span class="line-through">{{ is_bool($old[$key]) ? ($old[$key] ? 'yes' : 'no') : Str::limit((string) $old[$key], 40) }}</span> → @endif
                                        {{ is_bool($value) ? ($value ? 'yes' : 'no') : Str::limit((string) $value, 60) }}
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-gray-500">Nothing logged yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $activities->links() }}</div>
</x-app-layout>

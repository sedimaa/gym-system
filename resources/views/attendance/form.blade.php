<x-app-layout>
    <x-slot name="title">{{ $attendance ? 'Correct attendance' : 'Add attendance record' }}</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">{{ $attendance ? 'Correct attendance' : 'Add missed attendance record' }}</h1>
    </x-slot>

    <div class="card card-body max-w-xl">
        <p class="mb-5 text-sm text-gray-600">
            Admin only. A note is required and the change is saved in the activity log. Records added here never use a per-session bundle session.
        </p>

        <form method="POST" action="{{ $attendance ? route('attendance.update', $attendance) : route('attendance.records.store') }}" class="space-y-4">
            @csrf
            @if ($attendance) @method('PUT') @endif

            <div>
                <label for="member_id" class="form-label">Member</label>
                @if ($attendance)
                    <p class="mt-1 font-medium">{{ $member->full_name }} <span class="font-mono text-xs text-gray-500">{{ $member->member_code }}</span></p>
                @else
                    <select id="member_id" name="member_id" class="form-select" required>
                        <option value="">Choose a member…</option>
                        @foreach ($members as $m)
                            <option value="{{ $m->id }}" @selected((int) old('member_id', $member?->id) === $m->id)>{{ $m->last_name }}, {{ $m->first_name }} ({{ $m->member_code }})</option>
                        @endforeach
                    </select>
                    @error('member_id') <p class="form-error">{{ $message }}</p> @enderror
                @endif
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="checked_in_at" class="form-label">Date and time</label>
                    <input id="checked_in_at" name="checked_in_at" type="datetime-local" class="form-input" max="{{ now()->format('Y-m-d\TH:i') }}"
                           value="{{ old('checked_in_at', $attendance?->checked_in_at->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}" required>
                    @error('checked_in_at') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}" @selected(old('status', $attendance?->status->value ?? 'manual') === $s->value)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="note" class="form-label">Note (required)</label>
                <input id="note" name="note" class="form-input" maxlength="255" value="{{ old('note', $attendance?->note) }}" required placeholder="e.g. PC was off this morning; logged from paper sheet">
                @error('note') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button class="btn-primary">{{ $attendance ? 'Save correction' : 'Add record' }}</button>
                <a href="{{ route('attendance.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </form>

        @if ($attendance)
            @can('delete', $attendance)
                <form method="POST" action="{{ route('attendance.destroy', $attendance) }}" class="mt-6 border-t border-gray-100 pt-4"
                      onsubmit="return confirm('Delete this attendance record?{{ $attendance->session_used ? ' The session it used will be given back.' : '' }}');">
                    @csrf
                    @method('DELETE')
                    <button class="link-danger text-sm">Delete this record</button>
                </form>
            @endcan
        @endif
    </div>
</x-app-layout>

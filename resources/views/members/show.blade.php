<x-app-layout>
    <x-slot name="title">{{ $member->full_name }}</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Member profile</h1>
    </x-slot>

    {{-- Header card --}}
    <div class="card card-body mb-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-slate-800 text-lg font-semibold text-white">{{ $member->initials }}</div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-semibold text-gray-900">{{ $member->full_name }}</h2>
                        @isset($status)
                            <x-membership-status :status="$status" />
                        @else
                            @if (! $member->is_active) <span class="badge-gray">Deactivated</span> @endif
                        @endisset
                    </div>
                    <p class="text-sm text-gray-500">
                        <span class="font-mono">{{ $member->member_code }}</span>
                        · Member since {{ $member->joined_on->format('M j, Y') }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @stack('member-actions')
                @can('update', $member)
                    <a href="{{ route('members.edit', $member) }}" class="btn-secondary">Edit details</a>
                @endcan
                @can('deactivate', $member)
                    @if ($member->is_active)
                        <button type="button" class="btn-secondary text-red-700" x-data @click="$dispatch('open-modal', 'deactivate-member')">Deactivate</button>
                    @else
                        <form method="POST" action="{{ route('members.reactivate', $member) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn-secondary text-green-700">Reactivate</button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>

        @if (! $member->is_active)
            <div class="mt-4 rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700">
                Deactivated on {{ $member->deactivated_at?->format('M j, Y g:i A') ?? '—' }}@if ($member->deactivation_reason) · Reason: {{ $member->deactivation_reason }}@endif.
                This member can't check in until reactivated. All records are kept.
            </div>
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Details --}}
        <div class="card card-body h-fit">
            <h3 class="card-title mb-4">Details</h3>
            <dl class="space-y-3 text-sm">
                @foreach ([
                    'Mobile' => $member->phone,
                    'E-mail' => $member->email,
                    'Gender' => $member->gender ? ucfirst($member->gender) : null,
                    'Birthdate' => $member->birthdate ? $member->birthdate->format('M j, Y').' ('.$member->age().' yrs)' : null,
                    'Address' => $member->address,
                    'Emergency contact' => trim(($member->emergency_contact_name ?? '').' '.($member->emergency_contact_phone ?? '')) ?: null,
                ] as $label => $value)
                    <div class="grid grid-cols-3 gap-2">
                        <dt class="text-gray-500">{{ $label }}</dt>
                        <dd class="col-span-2 text-gray-900">{{ $value ?? '—' }}</dd>
                    </div>
                @endforeach
                @if ($member->notes)
                    <div>
                        <dt class="text-gray-500">Notes</dt>
                        <dd class="mt-1 whitespace-pre-line text-gray-900">{{ $member->notes }}</dd>
                    </div>
                @endif
            </dl>

            @can('delete', $member)
                <form method="POST" action="{{ route('members.destroy', $member) }}" class="mt-6 border-t border-gray-100 pt-4"
                      onsubmit="return confirm({{ Js::from('Permanently delete '.$member->full_name.'? This cannot be undone.') }});">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="link-danger text-sm">Delete member</button>
                    <p class="form-help">Only possible for members with no payments or attendance.</p>
                </form>
            @endcan
        </div>

        <div class="space-y-6 xl:col-span-2">
            @stack('member-sections')
        </div>
    </div>

    @can('deactivate', $member)
        <x-modal name="deactivate-member" focusable>
            <form method="POST" action="{{ route('members.deactivate', $member) }}" class="p-6">
                @csrf
                @method('PATCH')
                <h2 class="text-lg font-semibold text-gray-900">Deactivate {{ $member->full_name }}?</h2>
                <p class="mt-1 text-sm text-gray-600">The member won't be able to check in. Payments, attendance and other records are kept, and you can reactivate any time.</p>
                <label for="deactivation_reason" class="form-label mt-4">Reason <span class="font-normal text-gray-400">(optional)</span></label>
                <input id="deactivation_reason" name="deactivation_reason" class="form-input" maxlength="255" placeholder="e.g. Moved away">
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">Cancel</button>
                    <button type="submit" class="btn-danger">Deactivate</button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-app-layout>

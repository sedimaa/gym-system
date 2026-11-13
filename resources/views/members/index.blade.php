<x-app-layout>
    <x-slot name="title">Members</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Members</h1>
    </x-slot>

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <form method="GET" action="{{ route('members.index') }}" class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-end">
            <div class="flex-1 sm:max-w-sm">
                <label for="q" class="sr-only">Search</label>
                <input id="q" name="q" type="search" value="{{ $q }}" class="form-input mt-0" placeholder="Search name, member code or phone" autofocus>
            </div>
            <div>
                <label for="status" class="sr-only">Show</label>
                <select id="status" name="status" class="form-select mt-0" onchange="this.form.submit()">
                    @foreach ($statusOptions ?? ['active' => 'Active accounts', 'inactive' => 'Deactivated', 'all' => 'All members'] as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-secondary">Search</button>
            @if ($q !== '' || $status !== 'current')
                <a href="{{ route('members.index') }}" class="self-center text-sm text-gray-600 hover:text-gray-900">Clear</a>
            @endif
        </form>

        @can('create', App\Models\Member::class)
            <a href="{{ route('members.create') }}" class="btn-primary">+ Register member</a>
        @endcan
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Phone</th>
                        @isset($showMembershipColumns)
                            <th>Plan</th>
                            <th>Expires</th>
                        @endisset
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        <tr class="cursor-pointer" onclick="window.location='{{ route('members.show', $member) }}'">
                            <td class="whitespace-nowrap font-mono text-xs text-gray-600">{{ $member->member_code }}</td>
                            <td>
                                <a href="{{ route('members.show', $member) }}" class="font-medium text-gray-900 hover:underline">{{ $member->last_name }}, {{ $member->first_name }}</a>
                            </td>
                            <td class="whitespace-nowrap">{{ $member->phone ?? '—' }}</td>
                            @isset($showMembershipColumns)
                                <td>{{ $member->currentMembership?->plan?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap">{{ $member->currentMembership?->expiry_date?->format('M j, Y') ?? '—' }}</td>
                            @endisset
                            <td>
                                @if (isset($statuses))
                                    <x-membership-status :status="$statuses[$member->id]" />
                                @elseif ($member->is_active)
                                    <span class="badge-green">Active</span>
                                @else
                                    <span class="badge-gray">Deactivated</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-500">
                                {{ $q !== '' ? 'No members match your search.' : 'No members yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $members->links() }}</div>
</x-app-layout>

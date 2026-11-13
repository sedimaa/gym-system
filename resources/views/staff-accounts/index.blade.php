<x-app-layout>
    <x-slot name="title">Staff accounts</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Staff accounts</h1>
    </x-slot>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-600">Every staff member gets their own login. Disable an account when someone leaves; their records are kept.</p>
        @can('create', App\Models\User::class)
            <a href="{{ route('staff-accounts.create') }}"
               class="inline-flex items-center justify-center rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                + Add account
            </a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">E-mail (login)</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($accounts as $account)
                        <tr @class(['text-gray-400' => ! $account->is_active])>
                            <td class="px-4 py-3 font-medium">
                                {{ $account->name }}
                                @if ($account->is(Auth::user()))
                                    <span class="ml-1 text-xs font-normal text-gray-500">(you)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $account->email }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-amber-100 text-amber-800' => $account->isAdmin(),
                                    'bg-slate-100 text-slate-700' => ! $account->isAdmin(),
                                ])>{{ $account->role->label() }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($account->is_active)
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Active</span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Disabled</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    @can('update', $account)
                                        <a href="{{ route('staff-accounts.edit', $account) }}" class="font-medium text-slate-700 hover:text-slate-900">Edit</a>
                                    @endcan
                                    @can('toggleActive', $account)
                                        <form method="POST" action="{{ route('staff-accounts.toggle-active', $account) }}"
                                              onsubmit="return confirm({{ Js::from(($account->is_active ? 'Disable ' : 'Enable ').$account->name."'s account?") }});">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" @class([
                                                'font-medium',
                                                'text-red-600 hover:text-red-800' => $account->is_active,
                                                'text-green-700 hover:text-green-900' => ! $account->is_active,
                                            ])>{{ $account->is_active ? 'Disable' : 'Enable' }}</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

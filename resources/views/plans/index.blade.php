<x-app-layout>
    <x-slot name="title">Plans & prices</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Plans & prices</h1>
    </x-slot>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-600">Plans members can buy, plus the day pass used for walk-ins. Price changes only affect new payments.</p>
        @can('create', App\Models\Plan::class)
            <a href="{{ route('plans.create') }}" class="btn-primary">+ Add plan</a>
        @endcan
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Type</th>
                        <th class="text-right">Price</th>
                        <th>Validity</th>
                        <th>Sessions</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plans as $plan)
                        <tr @class(['text-gray-400' => ! $plan->is_active])>
                            <td>
                                <div class="font-medium">{{ $plan->name }}</div>
                                @if ($plan->description)
                                    <div class="text-xs text-gray-500">{{ Str::limit($plan->description, 80) }}</div>
                                @endif
                            </td>
                            <td>{{ $plan->type->label() }}</td>
                            <td class="text-right font-medium tabular-nums">@peso($plan->price)</td>
                            <td>{{ $plan->durationLabel() }}</td>
                            <td>{{ $plan->session_count ?? '—' }}</td>
                            <td>
                                @if ($plan->is_active)
                                    <span class="badge-green">For sale</span>
                                @else
                                    <span class="badge-gray">Not for sale</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-3">
                                    @can('update', $plan)
                                        <a href="{{ route('plans.edit', $plan) }}" class="link">Edit</a>
                                    @endcan
                                    @can('delete', $plan)
                                        <form method="POST" action="{{ route('plans.destroy', $plan) }}"
                                              onsubmit="return confirm({{ Js::from('Delete the plan "'.$plan->name.'"?') }});">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="link-danger">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-gray-500">No plans yet. Add your first plan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

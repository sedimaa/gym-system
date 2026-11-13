<x-app-layout>
    <x-slot name="title">Edit plan</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Edit plan: {{ $plan->name }}</h1>
    </x-slot>

    <div class="card card-body max-w-2xl">
        <form method="POST" action="{{ route('plans.update', $plan) }}">
            @csrf
            @method('PUT')
            @include('plans._form')

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="btn-primary">Save changes</button>
                <a href="{{ route('plans.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>

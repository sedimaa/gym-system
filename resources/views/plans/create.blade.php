<x-app-layout>
    <x-slot name="title">Add plan</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Add plan</h1>
    </x-slot>

    <div class="card card-body max-w-2xl">
        <form method="POST" action="{{ route('plans.store') }}">
            @csrf
            @include('plans._form')

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="btn-primary">Create plan</button>
                <a href="{{ route('plans.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>

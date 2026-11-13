<x-app-layout>
    <x-slot name="title">Edit account</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Edit account: {{ $account->name }}</h1>
    </x-slot>

    <div class="max-w-2xl rounded-lg bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('staff-accounts.update', $account) }}">
            @csrf
            @method('PUT')
            @include('staff-accounts._form')

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>Save changes</x-primary-button>
                <a href="{{ route('staff-accounts.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>

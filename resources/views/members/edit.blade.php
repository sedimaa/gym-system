<x-app-layout>
    <x-slot name="title">Edit member</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Edit member: {{ $member->full_name }} <span class="font-mono text-sm font-normal text-gray-500">{{ $member->member_code }}</span></h1>
    </x-slot>

    <div class="card card-body max-w-4xl">
        <form method="POST" action="{{ route('members.update', $member) }}">
            @csrf
            @method('PUT')
            @include('members._form')

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="btn-primary">Save changes</button>
                <a href="{{ route('members.show', $member) }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>

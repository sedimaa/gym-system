<x-app-layout>
    <x-slot name="title">Register member</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Register member</h1>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card card-body xl:col-span-2">
            <form method="POST" action="{{ route('members.store') }}">
                @csrf
                <p class="mb-5 text-sm text-gray-600">The member code (e.g. <span class="font-mono">{{ App\Models\Member::nextMemberCode() }}</span>) is assigned automatically when you save.</p>
                @include('members._form')

                <div class="mt-6 flex items-center gap-3">
                    <button type="submit" class="btn-primary">Register member</button>
                    <a href="{{ route('members.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                </div>
            </form>
        </div>

        @include('members._rules')
    </div>
</x-app-layout>

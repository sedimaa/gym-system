<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Dashboard</h1>
    </x-slot>

    {{-- Placeholder: the real dashboard (income, attendance, expiring members) comes in Module 7. --}}
    <div class="rounded-lg bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold">Magandang araw, {{ Str::before(Auth::user()->name, ' ') }}!</h2>
        <p class="mt-1 text-sm text-gray-600">
            You are signed in as <span class="font-medium">{{ Auth::user()->role->label() }}</span>.
            Today is {{ now()->format('l, F j, Y') }}.
        </p>
        <p class="mt-4 text-sm text-gray-500">
            The daily summary (income, check-ins, expiring members) will appear here once those modules are built.
        </p>
    </div>
</x-app-layout>

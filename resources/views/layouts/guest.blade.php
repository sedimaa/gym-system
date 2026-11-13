<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ setting('gym_name') }}</title>

        {{-- Offline-first: no CDN fonts or scripts; everything is bundled by Vite. --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center bg-slate-900 px-4 py-10">
            <div class="flex flex-col items-center gap-3 text-white">
                @if (App\Support\Branding::logoPath())
                    <img src="{{ route('branding.logo') }}?v={{ urlencode(setting('gym_logo')) }}" alt="" class="h-20 w-20 rounded-lg bg-white object-contain p-1">
                @else
                    <x-application-logo class="h-14 w-14 text-amber-400" />
                @endif
                <h1 class="text-xl font-semibold tracking-tight">{{ setting('gym_name') }}</h1>
            </div>

            <div class="mt-6 w-full overflow-hidden rounded-lg bg-white px-6 py-6 shadow-xl sm:max-w-md">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>

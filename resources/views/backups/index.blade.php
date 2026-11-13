<x-app-layout>
    <x-slot name="title">Backups</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Backups</h1>
    </x-slot>

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="card card-body lg:col-span-2">
            <h2 class="card-title">Back up now</h2>
            <p class="mt-1 text-sm text-gray-600">Saves the whole database (members, payments, attendance, settings, accounts) and the logo into one .zip file.</p>
            <p class="mt-2 text-xs text-gray-500">Folder: <span class="font-mono">{{ $directory }}</span> · change it in <a href="{{ route('settings.edit') }}" class="link">Settings</a>.</p>
            <form method="POST" action="{{ route('backups.store') }}" class="mt-4">
                @csrf
                <button class="btn-primary">Back up now</button>
            </form>
        </div>
        <div class="card card-body text-sm text-gray-600">
            <h2 class="card-title text-gray-900">Automatic backup</h2>
            <p class="mt-1">A backup is made once a day, the first hour the PC is on, as long as the scheduled task is set up on this computer (see README). Older backups are thinned out automatically: all from the last 7 days, then one per day for 30 days, then weekly and monthly.</p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Backup</th><th>Date</th><th class="text-right">Size</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($backups as $i => $backup)
                        <tr>
                            <td class="font-mono text-xs">{{ $backup['file'] }} @if ($i === 0)<span class="badge-green ml-1 font-sans">Latest</span>@endif</td>
                            <td class="whitespace-nowrap">{{ $backup['date']->format('D, M j, Y g:i A') }}</td>
                            <td class="text-right">{{ Number::fileSize($backup['size'], 1) }}</td>
                            <td>
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('backups.download', $backup['file']) }}" class="link text-sm">Download</a>
                                    <button type="button" class="font-medium text-amber-700 hover:text-amber-900 text-sm" x-data
                                            @click="$dispatch('open-modal', 'restore'); $dispatch('restore-file', @js($backup['file']))">Restore</button>
                                    @can('delete-records')
                                        <form method="POST" action="{{ route('backups.destroy', $backup['file']) }}" onsubmit="return confirm('Delete this backup file?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="link-danger text-sm">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-gray-500">No backups yet. Click "Back up now".</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-modal name="restore" :show="$errors->has('confirmation')" focusable>
        <form method="POST" action="{{ route('backups.restore') }}" class="p-6"
              x-data="{ file: @js(old('file', '')), typed: '' }" @restore-file.window="file = $event.detail">
            @csrf
            <h2 class="text-lg font-semibold text-gray-900">Restore this backup?</h2>
            <p class="mt-1 font-mono text-xs text-gray-600" x-text="file"></p>
            <div class="mt-3 rounded-md bg-red-50 px-3 py-3 text-sm text-red-800">
                <p class="font-semibold">All current data will be replaced by the data in this backup.</p>
                <p class="mt-1">Anything recorded after this backup was made (payments, check-ins, new members) will be gone from the system. A safety backup of the current data is made first, and everyone will need to log in again.</p>
            </div>
            <input type="hidden" name="file" :value="file">
            <label for="confirmation" class="form-label mt-4">Type <span class="font-mono font-bold">RESTORE</span> to confirm</label>
            <input id="confirmation" name="confirmation" class="form-input font-mono" x-model="typed" autocomplete="off">
            @error('confirmation') <p class="form-error">{{ $message }}</p> @enderror
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" class="btn-secondary" x-on:click="$dispatch('close')">Cancel</button>
                <button class="btn-danger" :disabled="typed !== 'RESTORE'">Restore</button>
            </div>
        </form>
    </x-modal>
</x-app-layout>

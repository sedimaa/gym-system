<x-app-layout>
    <x-slot name="title">Settings</x-slot>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Settings</h1>
    </x-slot>

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        <div class="card card-body space-y-4">
            <h2 class="card-title">Gym information</h2>
            <div>
                <label for="gym_name" class="form-label">Gym name</label>
                <input id="gym_name" name="gym_name" class="form-input" value="{{ old('gym_name', $values['gym_name']) }}" required maxlength="100">
                @error('gym_name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="gym_address" class="form-label">Address</label>
                    <input id="gym_address" name="gym_address" class="form-input" value="{{ old('gym_address', $values['gym_address']) }}" maxlength="255">
                </div>
                <div>
                    <label for="gym_phone" class="form-label">Contact number</label>
                    <input id="gym_phone" name="gym_phone" class="form-input" value="{{ old('gym_phone', $values['gym_phone']) }}" maxlength="60">
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                @if ($hasLogo)
                    <img src="{{ route('branding.logo') }}?v={{ urlencode($values['gym_logo']) }}" alt="Logo" class="h-14 w-14 rounded border border-gray-200 object-contain">
                @endif
                <div>
                    <label for="logo" class="form-label">{{ $hasLogo ? 'Replace logo' : 'Logo' }}</label>
                    <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="mt-1 text-sm">
                    <p class="form-help">PNG or JPG, up to 1 MB. Shown in the menu, login page and receipts.</p>
                    @error('logo') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                @if ($hasLogo)
                    <label class="flex items-center gap-2 text-sm text-red-700"><input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300"> Remove logo</label>
                @endif
            </div>
        </div>

        <div class="card card-body space-y-4">
            <h2 class="card-title">Receipts and rules</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="receipt_header_note" class="form-label">Receipt header line <span class="font-normal text-gray-400">(e.g. TIN or business permit no.)</span></label>
                    <input id="receipt_header_note" name="receipt_header_note" class="form-input" value="{{ old('receipt_header_note', $values['receipt_header_note']) }}" maxlength="150">
                </div>
                <div>
                    <label for="receipt_footer" class="form-label">Receipt footer</label>
                    <input id="receipt_footer" name="receipt_footer" class="form-input" value="{{ old('receipt_footer', $values['receipt_footer']) }}" maxlength="255">
                </div>
            </div>
            <div>
                <label for="gym_rules" class="form-label">Gym rules</label>
                <textarea id="gym_rules" name="gym_rules" rows="6" class="form-input" maxlength="3000" placeholder="One rule per line">{{ old('gym_rules', $values['gym_rules']) }}</textarea>
                <p class="form-help">Shown when registering a member and printed at the bottom of receipts.</p>
            </div>
        </div>

        <div class="card card-body space-y-4">
            <h2 class="card-title">Membership rules</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="grace_days" class="form-label">Grace period (days)</label>
                    <input id="grace_days" name="grace_days" type="number" min="0" max="60" class="form-input" value="{{ old('grace_days', $values['grace_days']) }}" required>
                    <p class="form-help">Days after the expiry date that a member can still check in. 0 = none.</p>
                    @error('grace_days') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="expiring_soon_days" class="form-label">"Expiring soon" window (days)</label>
                    <input id="expiring_soon_days" name="expiring_soon_days" type="number" min="0" max="60" class="form-input" value="{{ old('expiring_soon_days', $values['expiring_soon_days']) }}" required>
                    <p class="form-help">Members show as expiring this many days before expiry.</p>
                    @error('expiring_soon_days') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <fieldset>
                <legend class="form-label">When a member renews the same plan, the new period…</legend>
                <div class="mt-2 space-y-2">
                    @foreach ($renewalModes as $mode => $label)
                        <label class="flex items-start gap-2 text-sm">
                            <input type="radio" name="renewal_start_mode" value="{{ $mode }}" class="mt-0.5 border-gray-300 text-slate-700" @checked(old('renewal_start_mode', $values['renewal_start_mode']) === $mode)>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="form-help">Changing to a different plan always starts on the payment date.</p>
            </fieldset>
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="walkin_price_editable" value="1" class="mt-0.5 rounded border-gray-300 text-slate-700" @checked(old('walkin_price_editable', $values['walkin_price_editable']))>
                <span>Allow changing the walk-in amount <span class="block text-xs text-gray-500">Off: walk-ins always pay the day-pass price from Plans &amp; prices.</span></span>
            </label>
        </div>

        <div class="card card-body space-y-2">
            <h2 class="card-title">Backup folder</h2>
            <input id="backup_path" name="backup_path" class="form-input font-mono text-xs" value="{{ old('backup_path', $values['backup_path']) }}" placeholder="{{ $defaultBackupPath }}">
            <p class="form-help">Leave blank to use the default above. Tip: use a USB drive or another disk (e.g. <span class="font-mono">D:\GymBackups</span>) so backups survive if this PC's drive fails.</p>
            @error('backup_path') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3">
            <button class="btn-primary">Save settings</button>
        </div>
    </form>
</x-app-layout>

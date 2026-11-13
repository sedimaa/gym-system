@php
    $account = $account ?? null;
    $editingSelf = $account && $account->is(Auth::user());
@endphp

<div class="space-y-5">
    <div>
        <x-input-label for="name" value="Full name" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $account?->name)" required autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="email" value="E-mail (used to log in)" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $account?->email)" required />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="role" value="Role" />
        <select id="role" name="role" @disabled($editingSelf)
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 disabled:bg-gray-100">
            @foreach ($roles as $role)
                <option value="{{ $role->value }}" @selected(old('role', $account?->role?->value ?? 'staff') === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </select>
        @if ($editingSelf)
            {{-- Disabled selects aren't submitted; keep the value. --}}
            <input type="hidden" name="role" value="{{ $account->role->value }}">
            <p class="mt-1 text-xs text-gray-500">You can't change your own role.</p>
        @else
            <p class="mt-1 text-xs text-gray-500">Staff: members, payments, walk-ins, attendance. Admin: everything, including plans, prices, settings and backups.</p>
        @endif
        <x-input-error :messages="$errors->get('role')" class="mt-2" />
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="password" :value="$account ? 'New password' : 'Password'" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" :required="! $account" />
            @if ($account)
                <p class="mt-1 text-xs text-gray-500">Leave blank to keep the current password.</p>
            @endif
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" :required="! $account" />
        </div>
    </div>
</div>

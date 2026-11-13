@php
    $field = fn ($name) => old($name, $member->{$name} instanceof \Carbon\CarbonInterface ? $member->{$name}->toDateString() : $member->{$name});
@endphp

<div class="space-y-6">
    <fieldset>
        <legend class="card-title mb-3">Personal details</legend>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="first_name" class="form-label">First name</label>
                <input id="first_name" name="first_name" class="form-input" value="{{ $field('first_name') }}" required maxlength="60" autofocus>
                @error('first_name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="middle_name" class="form-label">Middle name <span class="font-normal text-gray-400">(optional)</span></label>
                <input id="middle_name" name="middle_name" class="form-input" value="{{ $field('middle_name') }}" maxlength="60">
                @error('middle_name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="last_name" class="form-label">Last name</label>
                <input id="last_name" name="last_name" class="form-input" value="{{ $field('last_name') }}" required maxlength="60">
                @error('last_name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="gender" class="form-label">Gender</label>
                <select id="gender" name="gender" class="form-select">
                    <option value="">—</option>
                    <option value="male" @selected($field('gender') === 'male')>Male</option>
                    <option value="female" @selected($field('gender') === 'female')>Female</option>
                </select>
                @error('gender') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="birthdate" class="form-label">Birthdate</label>
                <input id="birthdate" name="birthdate" type="date" class="form-input" value="{{ $field('birthdate') }}" max="{{ today()->subDay()->toDateString() }}">
                @error('birthdate') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="joined_on" class="form-label">Date joined</label>
                <input id="joined_on" name="joined_on" type="date" class="form-input" value="{{ $field('joined_on') }}" max="{{ today()->toDateString() }}" required>
                @error('joined_on') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend class="card-title mb-3">Contact</legend>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="phone" class="form-label">Mobile number</label>
                <input id="phone" name="phone" type="tel" class="form-input" value="{{ $field('phone') }}" maxlength="20" placeholder="0917 123 4567">
                @error('phone') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="email" class="form-label">E-mail <span class="font-normal text-gray-400">(optional)</span></label>
                <input id="email" name="email" type="email" class="form-input" value="{{ $field('email') }}">
                @error('email') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label for="address" class="form-label">Address</label>
                <input id="address" name="address" class="form-input" value="{{ $field('address') }}" maxlength="255" placeholder="Street, purok, barangay, city">
                @error('address') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="emergency_contact_name" class="form-label">Emergency contact</label>
                <input id="emergency_contact_name" name="emergency_contact_name" class="form-input" value="{{ $field('emergency_contact_name') }}" maxlength="120" placeholder="Name and relationship">
                @error('emergency_contact_name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="emergency_contact_phone" class="form-label">Emergency contact number</label>
                <input id="emergency_contact_phone" name="emergency_contact_phone" type="tel" class="form-input" value="{{ $field('emergency_contact_phone') }}" maxlength="20">
                @error('emergency_contact_phone') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </fieldset>

    <div>
        <label for="notes" class="form-label">Notes <span class="font-normal text-gray-400">(health concerns, goals, etc.)</span></label>
        <textarea id="notes" name="notes" rows="3" class="form-input" maxlength="2000">{{ $field('notes') }}</textarea>
        @error('notes') <p class="form-error">{{ $message }}</p> @enderror
    </div>
</div>

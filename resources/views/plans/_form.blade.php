@php
    $defaults = collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->defaultDurationDays()]);
@endphp

<div class="space-y-5"
     x-data="{
        type: @js(old('type', $plan->type?->value ?? 'monthly')),
        duration: @js((string) old('duration_days', $plan->duration_days ?? 30)),
        defaults: @js($defaults),
        isNew: @js(! $plan->exists),
        changeType() { if (this.isNew || this.type === 'day_pass') this.duration = String(this.defaults[this.type]); }
     }">
    <div>
        <label for="name" class="form-label">Plan name</label>
        <input id="name" name="name" type="text" class="form-input" value="{{ old('name', $plan->name) }}" required maxlength="100" placeholder="e.g. Monthly Membership">
        @error('name') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="type" class="form-label">Type</label>
            <select id="type" name="type" class="form-select" x-model="type" @change="changeType()">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </select>
            @error('type') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="price" class="form-label">Price (₱)</label>
            <input id="price" name="price" type="number" step="0.01" min="0" class="form-input" value="{{ old('price', $plan->price) }}" required>
            @error('price') <p class="form-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div x-show="type !== 'day_pass'">
            <label for="duration_days" class="form-label">Validity (days)</label>
            <input id="duration_days" name="duration_days" type="number" min="1" max="3650" class="form-input" x-model="duration" required>
            <p class="form-help">Expiry date = start date + this many days.</p>
            @error('duration_days') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div x-show="type === 'per_session'" x-cloak>
            <label for="session_count" class="form-label">Number of sessions</label>
            <input id="session_count" name="session_count" type="number" min="1" max="500" class="form-input" value="{{ old('session_count', $plan->session_count) }}" :required="type === 'per_session'">
            <p class="form-help">Each check-in uses one session. The bundle also ends when the validity runs out.</p>
            @error('session_count') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <p x-show="type === 'day_pass'" x-cloak class="text-sm text-gray-600 sm:col-span-2">
            The day pass is the price for walk-in guests, valid for the day of the visit.
        </p>
    </div>

    <div>
        <label for="description" class="form-label">Description <span class="font-normal text-gray-400">(optional)</span></label>
        <textarea id="description" name="description" rows="2" class="form-input" maxlength="1000">{{ old('description', $plan->description) }}</textarea>
        @error('description') <p class="form-error">{{ $message }}</p> @enderror
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-slate-700 focus:ring-slate-500" @checked(old('is_active', $plan->is_active))>
        Available for sale
    </label>
</div>

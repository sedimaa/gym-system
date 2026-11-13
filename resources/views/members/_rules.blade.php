{{-- Gym rules panel, filled from Settings (Module 8). --}}
@php($rules = $gymRules ?? null)
@if ($rules)
    <aside class="card card-body h-fit">
        <h2 class="card-title">Gym rules</h2>
        <p class="form-help mb-3">Please go over these with the new member.</p>
        <div class="whitespace-pre-line text-sm text-gray-700">{{ $rules }}</div>
    </aside>
@endif

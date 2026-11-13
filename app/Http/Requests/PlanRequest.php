<?php

namespace App\Http\Requests;

use App\Enums\PlanType;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan
            ? $this->user()->can('update', $plan)
            : $this->user()->can('create', Plan::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);

        // Day passes are always valid for the day of the visit only.
        if ($this->input('type') === PlanType::DayPass->value) {
            $this->merge(['duration_days' => 1]);
        }

        if ($this->input('type') !== PlanType::PerSession->value) {
            $this->merge(['session_count' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('plans', 'name')->ignore($this->route('plan'))],
            'type' => ['required', Rule::enum(PlanType::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'session_count' => [
                Rule::requiredIf($this->input('type') === PlanType::PerSession->value),
                'nullable', 'integer', 'min:1', 'max:500',
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'duration_days' => 'validity (days)',
            'session_count' => 'number of sessions',
        ];
    }
}

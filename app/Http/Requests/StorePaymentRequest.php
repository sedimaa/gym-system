<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Payment::class);
    }

    protected function prepareForValidation(): void
    {
        // Staff always record payments for today (server clock); only the admin may backdate.
        if (! $this->user()->can('backdate-payments') || blank($this->input('paid_on'))) {
            $this->merge(['paid_on' => today()->toDateString()]);
        }

        $this->merge(['reference_no' => trim((string) $this->input('reference_no')) ?: null]);
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')->where('is_active', true)],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)->whereNot('type', 'day_pass')],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_no' => [Rule::requiredIf(fn () => PaymentMethod::tryFrom((string) $this->input('method'))?->needsReference() ?? false), 'nullable', 'string', 'max:60'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.exists' => 'Choose an active member. Deactivated members must be reactivated by the admin first.',
            'plan_id.exists' => 'Choose a plan that is available for sale.',
            'reference_no.required' => 'Enter the reference number for GCash, Maya or bank payments.',
        ];
    }

    public function member(): Member
    {
        return Member::findOrFail($this->validated('member_id'));
    }

    public function plan(): Plan
    {
        return Plan::findOrFail($this->validated('plan_id'));
    }
}

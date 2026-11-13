<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Walkin;
use App\Services\WalkinService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWalkinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Walkin::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')) ?: null,
            'phone' => trim((string) $this->input('phone')) ?: null,
            'reference_no' => trim((string) $this->input('reference_no')) ?: null,
        ]);
    }

    public function rules(): array
    {
        $editable = app(WalkinService::class)->priceIsEditable();

        return [
            'name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+()\-\s]{7,20}$/'],
            'amount' => $editable ? ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'] : ['nullable'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_no' => [Rule::requiredIf(fn () => PaymentMethod::tryFrom((string) $this->input('method'))?->needsReference() ?? false), 'nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! app(WalkinService::class)->dayPass()) {
                    $validator->errors()->add('plan', 'There is no active day-pass plan. The admin can add one under Plans & prices.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number, e.g. 0917 123 4567.',
            'reference_no.required' => 'Enter the reference number for GCash, Maya or bank payments.',
        ];
    }
}

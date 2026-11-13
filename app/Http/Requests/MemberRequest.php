<?php

namespace App\Http\Requests;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->route('member');

        return $member
            ? $this->user()->can('update', $member)
            : $this->user()->can('create', Member::class);
    }

    protected function prepareForValidation(): void
    {
        $trim = fn ($v) => is_string($v) ? (trim(preg_replace('/\s+/', ' ', $v)) ?: null) : $v;

        $this->merge(collect($this->only([
            'first_name', 'middle_name', 'last_name', 'phone', 'email', 'address',
            'emergency_contact_name', 'emergency_contact_phone', 'notes',
        ]))->map($trim)->all());
    }

    public function rules(): array
    {
        $phone = ['nullable', 'string', 'max:20', 'regex:/^[0-9+()\-\s]{7,20}$/'];

        return [
            'first_name' => ['required', 'string', 'max:60'],
            'middle_name' => ['nullable', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'birthdate' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'phone' => $phone,
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => $phone,
            'notes' => ['nullable', 'string', 'max:2000'],
            'joined_on' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number, e.g. 0917 123 4567.',
            'emergency_contact_phone.regex' => 'Enter a valid phone number, e.g. 0917 123 4567.',
        ];
    }
}

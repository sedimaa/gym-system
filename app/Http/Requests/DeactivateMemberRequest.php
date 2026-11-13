<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeactivateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deactivate', $this->route('member'));
    }

    public function rules(): array
    {
        return [
            'deactivation_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}

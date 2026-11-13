<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-backups');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'string', 'max:255'],
            'confirmation' => ['required', 'in:RESTORE'],
        ];
    }

    public function messages(): array
    {
        return ['confirmation.in' => 'Type RESTORE in capital letters to confirm.', 'confirmation.required' => 'Type RESTORE in capital letters to confirm.'];
    }
}

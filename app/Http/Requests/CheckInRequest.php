<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Attendance::class);
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'action' => ['required', 'in:allow,deny,override'],
            'note' => ['nullable', 'required_if:action,override', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['note.required_if' => 'Write the reason for letting this member in (e.g. "Will pay tomorrow").'];
    }
}

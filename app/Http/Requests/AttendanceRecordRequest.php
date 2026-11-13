<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin adds or corrects an attendance record. A note is always required. */
class AttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attendance = $this->route('attendance');

        return $attendance
            ? $this->user()->can('update', $attendance)
            : $this->user()->can('addRecord', Attendance::class);
    }

    public function rules(): array
    {
        return [
            'member_id' => [Rule::requiredIf(! $this->route('attendance')), 'integer', 'exists:members,id'],
            'checked_in_at' => ['required', 'date', 'before_or_equal:now'],
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'note' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'A note is required when adding or correcting attendance.',
            'checked_in_at.before_or_equal' => 'The time can\'t be in the future.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportRequest extends FormRequest
{
    public const TYPES = ['income' => 'Income', 'attendance' => 'Attendance', 'members' => 'New members & renewals'];

    public const PRESETS = [
        'today' => 'Today',
        'this_week' => 'This week',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'last_3_months' => 'Last 3 months',
        'this_year' => 'This year',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('view-reports');
    }

    protected function prepareForValidation(): void
    {
        $today = CarbonImmutable::today();

        [$from, $to] = match ($this->input('preset')) {
            'today' => [$today, $today],
            'this_week' => [$today->startOfWeek(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'last_3_months' => [$today->subMonthsNoOverflow(2)->startOfMonth(), $today],
            'this_year' => [$today->startOfYear(), $today],
            'this_month' => [$today->startOfMonth(), $today],
            default => [null, null],
        };

        $this->merge([
            'type' => $this->input('type', 'income'),
            'from' => $from?->toDateString() ?? $this->input('from', $today->startOfMonth()->toDateString()),
            'to' => $to?->toDateString() ?? $this->input('to', $today->toDateString()),
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'preset' => ['nullable', 'in:'.implode(',', array_keys(self::PRESETS))],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $validator->errors()->has('from') && ! $validator->errors()->has('to')
                && CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($this->input('to'))) > 366) {
                $validator->errors()->add('to', 'Choose a period of one year or less.');
            }
        }];
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('from'))->startOfDay();
    }

    public function to(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('to'))->startOfDay();
    }

    public function type(): string
    {
        return $this->validated('type');
    }
}

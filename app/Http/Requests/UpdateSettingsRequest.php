<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-settings');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'walkin_price_editable' => $this->boolean('walkin_price_editable'),
            'remove_logo' => $this->boolean('remove_logo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'gym_name' => ['required', 'string', 'max:100'],
            'gym_address' => ['nullable', 'string', 'max:255'],
            'gym_phone' => ['nullable', 'string', 'max:60'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['boolean'],
            'receipt_header_note' => ['nullable', 'string', 'max:150'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'gym_rules' => ['nullable', 'string', 'max:3000'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:60'],
            'expiring_soon_days' => ['required', 'integer', 'min:0', 'max:60'],
            'renewal_start_mode' => ['required', Rule::in(array_keys(config('gym.renewal_start_modes')))],
            'walkin_price_editable' => ['boolean'],
            'backup_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $path = trim((string) $this->input('backup_path'));

            if ($path === '' || $validator->errors()->has('backup_path')) {
                return;
            }

            if (! is_dir($path) && ! @mkdir($path, 0775, true)) {
                $validator->errors()->add('backup_path', 'This folder does not exist and could not be created.');
            } elseif (! is_writable($path)) {
                $validator->errors()->add('backup_path', 'The system cannot write to this folder.');
            }
        }];
    }

    public function attributes(): array
    {
        return ['logo' => 'logo', 'grace_days' => 'grace period', 'expiring_soon_days' => 'expiring-soon window'];
    }
}

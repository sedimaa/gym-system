<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateStaffAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('account'));
    }

    public function rules(): array
    {
        /** @var User $account */
        $account = $this->route('account');
        $editingSelf = $this->user()->is($account);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($account->id)],
            // An admin can't demote themselves (prevents locking the gym out).
            'role' => $editingSelf
                ? ['required', Rule::in([Role::Admin->value])]
                : ['required', Rule::enum(Role::class)],
            // Leave blank to keep the current password; fill in to reset a forgotten one.
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'You cannot remove your own admin role.',
        ];
    }
}

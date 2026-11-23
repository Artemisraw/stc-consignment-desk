<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateStaffRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => str((string) $this->input('email'))->trim()->lower()->toString(),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'role' => ['required', new Enum(Role::class)],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->user()->is($this->route('user')) && $this->input('role') !== Role::Owner->value) {
                $validator->errors()->add('role', 'You cannot demote your own account.');
            }
        }];
    }
}

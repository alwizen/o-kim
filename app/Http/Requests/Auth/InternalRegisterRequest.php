<?php

namespace App\Http\Requests\Auth;

use App\Services\Registration\RegistrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class InternalRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', Rule::in(array_keys(RegistrationService::internalRoleOptions()))],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'role.in' => 'Role tidak tersedia untuk pendaftaran mandiri.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'email' => 'email',
            'role' => 'role',
            'password' => 'kata sandi',
        ];
    }
}

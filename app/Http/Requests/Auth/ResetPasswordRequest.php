<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
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
            'token.required' => 'Tautan reset tidak valid. Silakan minta tautan baru.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.letters' => 'Kata sandi harus mengandung huruf.',
            'password.mixed' => 'Kata sandi harus mengandung huruf besar dan huruf kecil.',
            'password.numbers' => 'Kata sandi harus mengandung angka.',
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'email',
            'password' => 'kata sandi',
        ];
    }
}

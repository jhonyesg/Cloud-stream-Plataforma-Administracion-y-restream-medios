<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'password' => ['sometimes', 'required', 'prohibited'],
            'password_confirmation' => ['sometimes', 'required', 'prohibited'],
            'current_password' => ['sometimes', 'required', 'prohibited'],
        ];
    }

    public function messages(): array
    {
        $msg = 'Para cambiar tu contraseña usa el formulario "Cambiar contraseña" del menú superior.';

        return [
            'password.required' => $msg,
            'password.prohibited' => $msg,
            'password_confirmation.required' => $msg,
            'password_confirmation.prohibited' => $msg,
            'current_password.required' => $msg,
            'current_password.prohibited' => $msg,
        ];
    }
}

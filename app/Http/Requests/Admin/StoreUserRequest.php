<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique(User::class, 'username')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'display_name' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'string', Password::min(8)],
            'role' => ['required', Rule::in(['admin', 'client'])],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'owner_id' => ['nullable', 'string', Rule::exists(User::class, 'id')],
        ];
    }

    public function expectsJson(): bool
    {
        return true;
    }
}
<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique(User::class, 'username')->ignore($userId)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($userId)],
            'display_name' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', Password::min(8)],
            'role' => ['required', Rule::in(['admin', 'client'])],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'owner_id' => ['nullable', 'string', Rule::exists(User::class, 'id'), Rule::notIn([$userId])],
        ];
    }

    public function expectsJson(): bool
    {
        return true;
    }
}
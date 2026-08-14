<?php

namespace App\Http\Requests\Admin;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/', Rule::unique(Channel::class, 'slug')],
            'public_hls_url' => ['nullable', 'string', 'max:500', 'url'],
            'owner_id' => ['required', 'string', Rule::exists(User::class, 'id')],
            'status' => ['required', Rule::in(['active', 'draft', 'suspended', 'archived'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'root_path' => ['nullable', 'string', 'max:1024', 'regex:/^\/[A-Za-z0-9._\/\- ]+$/'],
            'storage_limit_gb' => ['nullable', 'numeric', 'min:0'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => ['string', Rule::exists(User::class, 'id')],
        ];
    }

    public function expectsJson(): bool
    {
        return true;
    }
}
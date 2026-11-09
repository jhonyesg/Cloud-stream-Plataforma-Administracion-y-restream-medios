<?php

namespace App\Http\Requests\Restream;

use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRestreamTargetScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date', 'after:starts_at'],
            'timezone' => ['sometimes', 'required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'thumbnail_path' => ['sometimes', 'nullable', 'string', 'max:512'],
            'thumbnail_media_id' => ['sometimes', 'nullable', 'uuid', 'exists:media_items,id'],
            'schedule_title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'schedule_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'platform_privacy' => ['sometimes', 'nullable', 'in:public,unlisted,private'],
            'keep_recording' => ['sometimes', 'nullable', 'boolean'],
            'enabled' => ['sometimes', 'boolean'],
            'stopped_by_user' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'ends_at.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'timezone.in' => 'La zona horaria debe ser un identificador IANA válido (ej. America/Bogota).',
        ];
    }

    public function expectsJson(): bool
    {
        return true;
    }
}

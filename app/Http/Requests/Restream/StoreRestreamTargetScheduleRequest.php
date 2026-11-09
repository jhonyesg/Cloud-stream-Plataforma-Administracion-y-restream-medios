<?php

namespace App\Http\Requests\Restream;

use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRestreamTargetScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'notes' => ['nullable', 'string', 'max:1000'],
            'thumbnail_path' => ['nullable', 'string', 'max:512'],
            'thumbnail_media_id' => ['nullable', 'uuid', 'exists:media_items,id'],
            'schedule_title' => ['nullable', 'string', 'max:150'],
            'schedule_description' => ['nullable', 'string', 'max:5000'],
            'platform_privacy' => ['nullable', 'in:public,unlisted,private'],
            'keep_recording' => ['nullable', 'boolean'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'starts_at.required' => 'La fecha de inicio es obligatoria.',
            'ends_at.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'timezone.required' => 'La zona horaria es obligatoria.',
            'timezone.in' => 'La zona horaria debe ser un identificador IANA válido (ej. America/Bogota).',
            'name.max' => 'El nombre no puede superar los 120 caracteres.',
            'notes.max' => 'Las notas no pueden superar los 1000 caracteres.',
        ];
    }

    public function expectsJson(): bool
    {
        return true;
    }
}

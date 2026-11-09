<?php

namespace App\Http\Requests\Restream;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Marker request used by routes that expose a manual "Detener ahora" or
 * "Arrancar ahora" action. The route handler does the work; the request
 * only enforces authentication and the JSON contract.
 *
 * Kept as its own class so future rate limits or audit hooks can be
 * added in one place without touching the controllers.
 */
class StopNowRestreamTargetScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [];
    }

    public function expectsJson(): bool
    {
        return true;
    }
}

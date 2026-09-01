<?php

namespace App\Services\Restream\Platform;

use App\Models\RestreamPlatformAccount;

class RestreamPlatformAccountException extends \RuntimeException
{
    public static function reconnectRequired(RestreamPlatformAccount $account): self
    {
        return new self(sprintf(
            'La conexión con %s expiró o fue revocada. Vuelve a conectar la cuenta.',
            $account->platformLabel(),
        ));
    }

    public static function broadcastCreationFailed(string $platform, string $reason): self
    {
        return new self(sprintf('No se pudo crear la transmisión en %s: %s', ucfirst($platform), $reason));
    }
}

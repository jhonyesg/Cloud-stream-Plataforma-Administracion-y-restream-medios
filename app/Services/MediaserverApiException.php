<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class MediaserverApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $endpoint,
        public readonly ?string $body = null,
        public readonly ?string $detail = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public static function unreachable(string $endpoint, ?Throwable $previous = null): self
    {
        return new self(
            message: "MediaServer Platform no responde en {$endpoint}",
            statusCode: 0,
            endpoint: $endpoint,
            body: null,
            detail: 'unreachable',
            previous: $previous,
        );
    }

    public static function authFailed(string $endpoint, ?string $body = null): self
    {
        return new self(
            message: 'Credenciales inválidas para MediaServer Platform',
            statusCode: 401,
            endpoint: $endpoint,
            body: $body,
            detail: 'auth_failed',
        );
    }

    public static function panelError(string $endpoint, int $statusCode, ?string $body = null): self
    {
        return new self(
            message: "MediaServer Platform devolvió HTTP {$statusCode} en {$endpoint}",
            statusCode: $statusCode,
            endpoint: $endpoint,
            body: $body,
            detail: 'panel_error',
        );
    }
}

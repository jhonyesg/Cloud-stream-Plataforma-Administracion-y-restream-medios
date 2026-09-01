<?php

namespace App\Services\Restream;

use RuntimeException;

class RestreamQuotaException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
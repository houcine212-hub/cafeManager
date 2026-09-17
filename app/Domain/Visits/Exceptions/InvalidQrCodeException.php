<?php

namespace App\Domain\Visits\Exceptions;

use RuntimeException;

class InvalidQrCodeException extends RuntimeException
{
    public function __construct(string $message = "Ce code QR est invalide ou a été révoqué.")
    {
        parent::__construct($message);
    }
}

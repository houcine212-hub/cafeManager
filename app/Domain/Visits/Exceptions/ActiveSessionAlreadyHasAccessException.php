<?php

namespace App\Domain\Visits\Exceptions;

use RuntimeException;

class ActiveSessionAlreadyHasAccessException extends RuntimeException
{
    public function __construct(string $message = "La table a déjà un accès client en cours de traitement.")
    {
        parent::__construct($message);
    }
}

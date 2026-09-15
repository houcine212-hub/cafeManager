<?php

namespace App\Domain\Ordering\Exceptions;

use RuntimeException;

class UnauthorizedGuestAccessException extends RuntimeException
{
    public function __construct(string $message = "L'accès invité n'est pas autorisé ou n'est plus actif pour cette session.")
    {
        parent::__construct($message);
    }
}

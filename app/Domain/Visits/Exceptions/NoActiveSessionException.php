<?php

namespace App\Domain\Visits\Exceptions;

use RuntimeException;

class NoActiveSessionException extends RuntimeException
{
    public function __construct(string $message = "Aucune session active n'est ouverte sur cette table.")
    {
        parent::__construct($message);
    }
}

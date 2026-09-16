<?php

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

class InvalidCheckoutStateException extends RuntimeException
{
    public function __construct(string $status)
    {
        parent::__construct("Impossible de figer l'addition. La session est actuellement au statut : {$status}.");
    }
}

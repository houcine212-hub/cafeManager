<?php

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

class PaymentAmountMismatchException extends RuntimeException
{
    public function __construct(string $expected, string $received)
    {
        parent::__construct("Le montant reçu ({$received} DH) ne correspond pas au total figé de la session ({$expected} DH).");
    }
}

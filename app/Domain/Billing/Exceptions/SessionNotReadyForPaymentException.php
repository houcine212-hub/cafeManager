<?php

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

class SessionNotReadyForPaymentException extends RuntimeException
{
    public function __construct(string $status)
    {
        parent::__construct("Impossible d'enregistrer un paiement : la session doit être au statut 'checkout' (statut actuel : {$status}).");
    }
}

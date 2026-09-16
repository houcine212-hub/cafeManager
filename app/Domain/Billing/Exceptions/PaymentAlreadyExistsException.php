<?php

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

class PaymentAlreadyExistsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Un paiement a déjà été enregistré pour cette session (D04 : un seul règlement par visite).");
    }
}
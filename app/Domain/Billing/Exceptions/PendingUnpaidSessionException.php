<?php

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

class PendingUnpaidSessionException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Impossible de fermer la session : aucun paiement enregistré et aucune exception 'impayé' autorisée n'a été fournie.");
    }
}

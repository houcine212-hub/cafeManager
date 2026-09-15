<?php

namespace App\Domain\Ordering\Exceptions;

use RuntimeException;

class SessionNotAcceptingOrdersException extends RuntimeException
{
    public function __construct(string $status)
    {
        parent::__construct("La session de la table n'accepte plus de commandes (Statut: {$status}).");
    }
}

<?php

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

class PendingOrdersExistException extends RuntimeException
{
    public function __construct(int $count)
    {
        parent::__construct("Impossible de figer l'addition : il reste {$count} commande(s) en cours de préparation ou non livrée(s).");
    }
}

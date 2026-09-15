<?php

namespace App\Domain\Ordering\Exceptions;

use RuntimeException;

class IdempotencyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Une tentative avec la même clé d'idempotence mais un contenu différent a été rejetée.");
    }
}

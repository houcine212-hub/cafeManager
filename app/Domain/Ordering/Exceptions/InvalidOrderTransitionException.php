<?php

namespace App\Domain\Ordering\Exceptions;

use RuntimeException;
use Throwable;

class InvalidOrderTransitionException extends RuntimeException
{
    public function __construct(
        private readonly string $from,
        private readonly string $to,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            "Transition de statut invalide : impossible de passer de [{$from}] à [{$to}].",
            $code,
            $previous
        );
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getTo(): string
    {
        return $this->to;
    }
}

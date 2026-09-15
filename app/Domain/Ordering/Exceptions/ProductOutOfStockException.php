<?php

namespace App\Domain\Ordering\Exceptions;

use RuntimeException;

class ProductOutOfStockException extends RuntimeException
{
    public function __construct(string $productName)
    {
        parent::__construct("La quantité restante en stock pour [{$productName}] est insuffisante.");
    }
}

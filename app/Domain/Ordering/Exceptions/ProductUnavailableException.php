<?php

namespace App\Domain\Ordering\Exceptions;

use RuntimeException;

class ProductUnavailableException extends RuntimeException
{
    public function __construct(string $productName)
    {
        parent::__construct("Le produit [{$productName}] n'est pas disponible actuellement.");
    }
}

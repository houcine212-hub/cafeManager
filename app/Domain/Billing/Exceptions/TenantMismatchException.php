<?php

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

class TenantMismatchException extends RuntimeException
{
    public function __construct(int $expectedCafeId, int $actualCafeId)
    {
        parent::__construct(sprintf(
            'Tenant mismatch: session cafe_id (%d) does not match active context (%d).',
            $actualCafeId,
            $expectedCafeId
        ));
    }
}

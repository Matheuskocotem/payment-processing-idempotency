<?php

declare(strict_types=1);

namespace App\Payment\Domain\Exception;

use RuntimeException;

/**
 * Base for gateway faults where whether the PSP actually processed the
 * charge is unknown. Callers must not infer success or failure from these —
 * the safe response is to retry with the same idempotency key later, or let
 * reconciliation converge the state.
 */
abstract class GatewayUnknownOutcomeException extends RuntimeException
{
    public function __construct(
        public readonly string $idempotencyKey,
        string $message,
    ) {
        parent::__construct($message);
    }
}

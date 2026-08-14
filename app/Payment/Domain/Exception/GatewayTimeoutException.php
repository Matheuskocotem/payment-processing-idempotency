<?php

declare(strict_types=1);

namespace App\Payment\Domain\Exception;

final class GatewayTimeoutException extends GatewayUnknownOutcomeException
{
    public static function forKey(string $idempotencyKey): self
    {
        return new self($idempotencyKey, sprintf(
            'Gateway timed out for idempotency key "%s". Outcome unknown — retry with the same key or reconcile.',
            $idempotencyKey,
        ));
    }
}

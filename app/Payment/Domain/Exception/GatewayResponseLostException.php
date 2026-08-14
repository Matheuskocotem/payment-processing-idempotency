<?php

declare(strict_types=1);

namespace App\Payment\Domain\Exception;

final class GatewayResponseLostException extends GatewayUnknownOutcomeException
{
    public static function forKey(string $idempotencyKey): self
    {
        return new self($idempotencyKey, sprintf(
            'Gateway response lost for idempotency key "%s". The charge may have gone through — retry with the same key or reconcile.',
            $idempotencyKey,
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Payment\Domain\Port\Gateway;

use App\Payment\Domain\ValueObject\Money;

/**
 * Outbound charge request sent to the PSP. `idempotencyKey` is a key this
 * service generates for its call to the provider (API→PSP idempotency) —
 * distinct from the client's `Idempotency-Key` header (client→API idempotency).
 */
final class GatewayChargeRequest
{
    public function __construct(
        public readonly string $idempotencyKey,
        public readonly string $paymentId,
        public readonly string $customerId,
        public readonly Money $money,
    ) {
    }
}

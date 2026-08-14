<?php

declare(strict_types=1);

namespace App\Payment\Application\DTO;

final class CreatePaymentInput
{
    public function __construct(
        public readonly string $customerId,
        public readonly int $amountCents,
        public readonly string $currency,
    ) {}
}

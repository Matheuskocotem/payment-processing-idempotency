<?php

declare(strict_types=1);

namespace App\Payment\Domain\ValueObject;

use InvalidArgumentException;

final class Money
{
    public function __construct(
        public readonly int $amountCents,
        public readonly string $currency,
    ) {
        if ($amountCents < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }
        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be ISO-4217 (3 letters).');
        }
    }

    public function equals(Money $other): bool
    {
        return $this->amountCents === $other->amountCents
            && $this->currency === $other->currency;
    }

    public function __toString(): string
    {
        return sprintf('%d %s', $this->amountCents, $this->currency);
    }
}
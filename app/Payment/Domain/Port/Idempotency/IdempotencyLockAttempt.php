<?php

declare(strict_types=1);

namespace App\Payment\Domain\Port\Idempotency;

/**
 * Outcome of trying to atomically acquire an idempotency lock. Adapters must
 * implement this by attempting an INSERT protected by a UNIQUE constraint on
 * the key and translating a constraint violation into conflict() — never by
 * checking existence first and inserting after.
 */
final class IdempotencyLockAttempt
{
    private function __construct(
        public readonly bool $acquired,
        public readonly ?IdempotencyRecord $existing,
    ) {}

    public static function acquired(): self
    {
        return new self(true, null);
    }

    public static function conflict(IdempotencyRecord $existing): self
    {
        return new self(false, $existing);
    }
}

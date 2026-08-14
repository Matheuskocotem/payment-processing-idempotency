<?php

declare(strict_types=1);

namespace App\Payment\Domain\Port;

use App\Payment\Domain\Port\Idempotency\IdempotencyLockAttempt;

/**
 * Port for the client→API idempotency ledger. The key space here is the
 * caller-supplied `Idempotency-Key` header, not the API→PSP key used by
 * PaymentGateway.
 */
interface IdempotencyStore
{
    /**
     * Attempts to atomically create a LOCKED record for the key. Must be
     * implemented as an INSERT guarded by a UNIQUE constraint — a duplicate
     * key is caught and returned as a conflict, never checked for up front.
     */
    public function acquireLock(string $key, string $fingerprint): IdempotencyLockAttempt;

    public function markCompleted(string $key, int $responseCode, string $responseBody): void;

    public function markFailed(string $key, int $responseCode, string $responseBody): void;
}

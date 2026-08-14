<?php

declare(strict_types=1);

namespace App\Payment\Domain\Port\Idempotency;

/**
 * key → fingerprint (SHA-256 of the canonicalized request body) + status +
 * the response to replay once the request has been resolved.
 */
final class IdempotencyRecord
{
    public function __construct(
        public readonly string $key,
        public readonly string $fingerprint,
        public readonly IdempotencyRecordStatus $status,
        public readonly ?int $responseCode,
        public readonly ?string $responseBody,
    ) {}

    public function hasFingerprint(string $fingerprint): bool
    {
        return hash_equals($this->fingerprint, $fingerprint);
    }
}

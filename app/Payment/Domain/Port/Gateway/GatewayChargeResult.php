<?php

declare(strict_types=1);

namespace App\Payment\Domain\Port\Gateway;

/**
 * Result of a charge the gateway definitively answered (accepted or
 * declined). Ambiguous outcomes (timeout, lost response) are never modeled
 * here — they are thrown as exceptions so callers can't accidentally treat
 * "unknown" as "declined".
 */
final class GatewayChargeResult
{
    private function __construct(
        public readonly GatewayChargeOutcome $outcome,
        public readonly ?string $providerRef,
        public readonly ?string $declineReason,
    ) {}

    public static function succeeded(string $providerRef): self
    {
        return new self(GatewayChargeOutcome::SUCCEEDED, $providerRef, null);
    }

    public static function declined(string $reason): self
    {
        return new self(GatewayChargeOutcome::DECLINED, null, $reason);
    }

    public function isSucceeded(): bool
    {
        return $this->outcome === GatewayChargeOutcome::SUCCEEDED;
    }
}

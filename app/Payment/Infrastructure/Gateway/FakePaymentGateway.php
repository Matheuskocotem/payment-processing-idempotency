<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Gateway;

use App\Payment\Domain\Exception\GatewayResponseLostException;
use App\Payment\Domain\Exception\GatewayTimeoutException;
use App\Payment\Domain\Port\Gateway\GatewayChargeRequest;
use App\Payment\Domain\Port\Gateway\GatewayChargeResult;
use App\Payment\Domain\Port\PaymentGateway;

/**
 * Controllable fake PSP for the MVP, behind the PaymentGateway port so a
 * real provider (e.g. Stripe) can replace it later without touching
 * Domain or Application. Bound as a singleton in the container so fault
 * injection configured by a test/controller is visible to the whole
 * request lifecycle.
 */
final class FakePaymentGateway implements PaymentGateway
{
    private const MODE_DECLINE = 'decline';

    private const MODE_TIMEOUT = 'timeout';

    private const MODE_RESPONSE_LOST = 'response_lost';

    private const MODE_DUPLICATE_CHARGE = 'duplicate_charge';

    /** @var array<string, array{mode: string, reason?: string}> */
    private array $injectedFaults = [];

    /**
     * One-shot fault applied to whichever idempotency key the next charge()
     * call receives, for callers that don't control key generation (e.g.
     * CreatePayment mints its own key internally).
     *
     * @var array{mode: string, reason?: string}|null
     */
    private ?array $nextFault = null;

    /**
     * Simulates the PSP's own idempotency ledger keyed by the idempotency
     * key we send it: replaying the same key returns the same result
     * instead of charging again.
     *
     * @var array<string, GatewayChargeResult>
     */
    private array $processedCharges = [];

    /** @var list<GatewayChargeRequest> */
    private array $receivedRequests = [];

    public function injectTimeout(string $idempotencyKey): void
    {
        $this->injectedFaults[$idempotencyKey] = ['mode' => self::MODE_TIMEOUT];
    }

    public function injectResponseLost(string $idempotencyKey): void
    {
        $this->injectedFaults[$idempotencyKey] = ['mode' => self::MODE_RESPONSE_LOST];
    }

    public function injectDecline(string $idempotencyKey, string $reason = 'insufficient_funds'): void
    {
        $this->injectedFaults[$idempotencyKey] = ['mode' => self::MODE_DECLINE, 'reason' => $reason];
    }

    /**
     * Simulates a misbehaving PSP that does NOT honor its own idempotency
     * key: every call charges again and returns a fresh provider
     * reference instead of replaying the first result. Proves the system
     * cannot rely on PSP-side idempotency alone and needs reconciliation
     * as a backstop.
     */
    public function injectDuplicateCharge(string $idempotencyKey): void
    {
        $this->injectedFaults[$idempotencyKey] = ['mode' => self::MODE_DUPLICATE_CHARGE];
    }

    public function injectTimeoutOnNextCharge(): void
    {
        $this->nextFault = ['mode' => self::MODE_TIMEOUT];
    }

    public function injectResponseLostOnNextCharge(): void
    {
        $this->nextFault = ['mode' => self::MODE_RESPONSE_LOST];
    }

    public function injectDeclineOnNextCharge(string $reason = 'insufficient_funds'): void
    {
        $this->nextFault = ['mode' => self::MODE_DECLINE, 'reason' => $reason];
    }

    public function reset(): void
    {
        $this->injectedFaults = [];
        $this->nextFault = null;
        $this->processedCharges = [];
        $this->receivedRequests = [];
    }

    public function charge(GatewayChargeRequest $request): GatewayChargeResult
    {
        $this->receivedRequests[] = $request;

        if ($this->nextFault !== null) {
            $fault = $this->nextFault;
            $this->nextFault = null;
        } else {
            $fault = $this->injectedFaults[$request->idempotencyKey] ?? null;
        }

        return match ($fault['mode'] ?? null) {
            self::MODE_TIMEOUT => throw GatewayTimeoutException::forKey($request->idempotencyKey),
            self::MODE_RESPONSE_LOST => $this->respondWithLostResponse($request),
            self::MODE_DUPLICATE_CHARGE => GatewayChargeResult::succeeded($this->generateProviderRef()),
            self::MODE_DECLINE => GatewayChargeResult::declined($fault['reason']),
            default => $this->processedCharges[$request->idempotencyKey]
                ??= GatewayChargeResult::succeeded($this->generateProviderRef()),
        };
    }

    public function chargeWasProcessed(string $idempotencyKey): bool
    {
        return isset($this->processedCharges[$idempotencyKey]);
    }

    /** @return list<GatewayChargeRequest> */
    public function receivedRequests(): array
    {
        return $this->receivedRequests;
    }

    private function respondWithLostResponse(GatewayChargeRequest $request): never
    {
        // The PSP did process the charge — we just never receive the response.
        $this->processedCharges[$request->idempotencyKey]
            ??= GatewayChargeResult::succeeded($this->generateProviderRef());

        throw GatewayResponseLostException::forKey($request->idempotencyKey);
    }

    private function generateProviderRef(): string
    {
        return 'fake_psp_'.bin2hex(random_bytes(8));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Payment\Infrastructure;

use App\Payment\Domain\Exception\GatewayResponseLostException;
use App\Payment\Domain\Exception\GatewayTimeoutException;
use App\Payment\Domain\Port\Gateway\GatewayChargeRequest;
use App\Payment\Domain\ValueObject\Money;
use App\Payment\Infrastructure\Gateway\FakePaymentGateway;
use PHPUnit\Framework\TestCase;

final class FakePaymentGatewayTest extends TestCase
{
    private function request(string $idempotencyKey): GatewayChargeRequest
    {
        return new GatewayChargeRequest($idempotencyKey, 'pay_1', 'cus_1', new Money(1_000, 'BRL'));
    }

    public function test_default_charge_succeeds_with_a_provider_reference(): void
    {
        $gateway = new FakePaymentGateway();

        $result = $gateway->charge($this->request('key-1'));

        $this->assertTrue($result->isSucceeded());
        $this->assertNotNull($result->providerRef);
    }

    public function test_charging_the_same_idempotency_key_twice_replays_the_first_result(): void
    {
        $gateway = new FakePaymentGateway();

        $first = $gateway->charge($this->request('key-1'));
        $second = $gateway->charge($this->request('key-1'));

        $this->assertSame($first->providerRef, $second->providerRef);
    }

    public function test_different_idempotency_keys_produce_different_charges(): void
    {
        $gateway = new FakePaymentGateway();

        $first = $gateway->charge($this->request('key-1'));
        $second = $gateway->charge($this->request('key-2'));

        $this->assertNotSame($first->providerRef, $second->providerRef);
    }

    public function test_injected_timeout_throws_and_records_no_processed_charge(): void
    {
        $gateway = new FakePaymentGateway();
        $gateway->injectTimeout('key-1');

        try {
            $gateway->charge($this->request('key-1'));
            $this->fail('Expected GatewayTimeoutException was not thrown.');
        } catch (GatewayTimeoutException $e) {
            $this->assertSame('key-1', $e->idempotencyKey);
        }

        $this->assertFalse($gateway->chargeWasProcessed('key-1'));
    }

    public function test_injected_response_lost_throws_but_the_charge_was_actually_processed(): void
    {
        $gateway = new FakePaymentGateway();
        $gateway->injectResponseLost('key-1');

        $this->expectException(GatewayResponseLostException::class);

        try {
            $gateway->charge($this->request('key-1'));
        } finally {
            $this->assertTrue($gateway->chargeWasProcessed('key-1'));
        }
    }

    public function test_injected_decline_returns_a_declined_result_with_reason(): void
    {
        $gateway = new FakePaymentGateway();
        $gateway->injectDecline('key-1', 'card_expired');

        $result = $gateway->charge($this->request('key-1'));

        $this->assertFalse($result->isSucceeded());
        $this->assertSame('card_expired', $result->declineReason);
    }

    public function test_injected_duplicate_charge_returns_a_fresh_provider_ref_on_every_call(): void
    {
        $gateway = new FakePaymentGateway();
        $gateway->injectDuplicateCharge('key-1');

        $first = $gateway->charge($this->request('key-1'));
        $second = $gateway->charge($this->request('key-1'));

        $this->assertTrue($first->isSucceeded());
        $this->assertTrue($second->isSucceeded());
        $this->assertNotSame($first->providerRef, $second->providerRef);
    }

    public function test_inject_on_next_charge_applies_regardless_of_key_and_only_once(): void
    {
        $gateway = new FakePaymentGateway();
        $gateway->injectDeclineOnNextCharge('card_expired');

        $first = $gateway->charge($this->request('unknown-key-generated-at-runtime'));
        $second = $gateway->charge($this->request('unknown-key-generated-at-runtime-2'));

        $this->assertFalse($first->isSucceeded());
        $this->assertSame('card_expired', $first->declineReason);
        $this->assertTrue($second->isSucceeded());
    }

    public function test_received_requests_are_recorded_in_order(): void
    {
        $gateway = new FakePaymentGateway();

        $gateway->charge($this->request('key-1'));
        $gateway->charge($this->request('key-2'));

        $keys = array_map(
            static fn (GatewayChargeRequest $r): string => $r->idempotencyKey,
            $gateway->receivedRequests(),
        );
        $this->assertSame(['key-1', 'key-2'], $keys);
    }
}

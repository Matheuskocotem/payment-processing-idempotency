<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Payment\Domain\Port\PaymentGateway;
use App\Payment\Infrastructure\Gateway\FakePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreatePaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => 'cus_1',
            'amount_cents' => 1_000,
            'currency' => 'BRL',
        ], $overrides);
    }

    public function test_it_requires_an_idempotency_key_header(): void
    {
        $this->postJson('/api/payments', $this->payload())
            ->assertStatus(400)
            ->assertJsonPath('error', 'idempotency_key_required');
    }

    public function test_it_creates_and_captures_a_payment_on_success(): void
    {
        $response = $this->postJson('/api/payments', $this->payload(), ['Idempotency-Key' => 'key-1']);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'CAPTURED');
        $response->assertJsonPath('customer_id', 'cus_1');
        $response->assertJsonPath('amount_cents', 1_000);
        $this->assertNotNull($response->json('provider_ref'));

        $this->assertDatabaseHas('payments', [
            'id' => $response->json('id'),
            'status' => 'CAPTURED',
        ]);
        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $response->json('id'),
            'type' => 'payment.initiated',
        ]);
        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $response->json('id'),
            'type' => 'payment.authorized',
        ]);
        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $response->json('id'),
            'type' => 'payment.captured',
        ]);
    }

    public function test_replaying_the_same_key_and_body_returns_the_stored_response_without_reprocessing(): void
    {
        $headers = ['Idempotency-Key' => 'key-1'];
        $payload = $this->payload();

        $first = $this->postJson('/api/payments', $payload, $headers);
        $second = $this->postJson('/api/payments', $payload, $headers);

        $second->assertStatus($first->getStatusCode());
        $second->assertHeader('Idempotent-Replay', 'true');
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, \App\Payment\Infrastructure\Persistence\Eloquent\PaymentModel::query()->count());
    }

    public function test_reusing_the_key_with_a_different_body_returns_422(): void
    {
        $headers = ['Idempotency-Key' => 'key-1'];
        $this->postJson('/api/payments', $this->payload(), $headers)->assertStatus(201);

        $this->postJson('/api/payments', $this->payload(['amount_cents' => 9_999]), $headers)
            ->assertStatus(422)
            ->assertJsonPath('error', 'idempotency_key_reused');
    }

    public function test_a_business_decline_transitions_the_payment_to_failed(): void
    {
        /** @var FakePaymentGateway $gateway */
        $gateway = $this->app->make(PaymentGateway::class);
        $gateway->injectDeclineOnNextCharge('card_expired');

        $response = $this->postJson('/api/payments', $this->payload(), ['Idempotency-Key' => 'key-1']);

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'FAILED');
        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $response->json('id'),
            'type' => 'payment.failed',
        ]);
    }

    public function test_a_gateway_timeout_leaves_the_payment_pending_instead_of_guessing(): void
    {
        /** @var FakePaymentGateway $gateway */
        $gateway = $this->app->make(PaymentGateway::class);
        $gateway->injectTimeoutOnNextCharge();

        $response = $this->postJson('/api/payments', $this->payload(), ['Idempotency-Key' => 'key-1']);

        $response->assertStatus(202);
        $response->assertJsonPath('status', 'PENDING');
        $this->assertDatabaseHas('payments', [
            'id' => $response->json('id'),
            'status' => 'PENDING',
        ]);
    }
}

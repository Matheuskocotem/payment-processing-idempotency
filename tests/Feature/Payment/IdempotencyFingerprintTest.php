<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The fingerprint is computed from a canonicalized (key-sorted) encoding of
 * the request body, so byte-for-byte formatting differences that don't
 * change the logical payload must not be treated as a different request.
 */
final class IdempotencyFingerprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_reordering_object_keys_produces_the_same_fingerprint(): void
    {
        $headers = ['Idempotency-Key' => 'key-1'];

        $first = $this->postJson('/api/payments', [
            'customer_id' => 'cus_1',
            'amount_cents' => 1_000,
            'currency' => 'BRL',
        ], $headers);
        $first->assertStatus(201);

        $second = $this->postJson('/api/payments', [
            'currency' => 'BRL',
            'amount_cents' => 1_000,
            'customer_id' => 'cus_1',
        ], $headers);

        $second->assertStatus(201);
        $second->assertHeader('Idempotent-Replay', 'true');
        $this->assertSame($first->json('id'), $second->json('id'));
    }

    public function test_a_semantically_different_amount_is_treated_as_a_different_fingerprint(): void
    {
        $headers = ['Idempotency-Key' => 'key-1'];

        $this->postJson('/api/payments', [
            'customer_id' => 'cus_1',
            'amount_cents' => 1_000,
            'currency' => 'BRL',
        ], $headers)->assertStatus(201);

        $this->postJson('/api/payments', [
            'customer_id' => 'cus_1',
            'amount_cents' => 1_001,
            'currency' => 'BRL',
        ], $headers)->assertStatus(422)
            ->assertJsonPath('error', 'idempotency_key_reused');
    }

    public function test_different_idempotency_keys_for_the_same_body_are_independent_requests(): void
    {
        $payload = [
            'customer_id' => 'cus_1',
            'amount_cents' => 1_000,
            'currency' => 'BRL',
        ];

        $first = $this->postJson('/api/payments', $payload, ['Idempotency-Key' => 'key-1']);
        $second = $this->postJson('/api/payments', $payload, ['Idempotency-Key' => 'key-2']);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertNotSame($first->json('id'), $second->json('id'));
    }
}

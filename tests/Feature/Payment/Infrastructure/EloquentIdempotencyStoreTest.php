<?php

declare(strict_types=1);

namespace Tests\Feature\Payment\Infrastructure;

use App\Payment\Domain\Port\Idempotency\IdempotencyRecordStatus;
use App\Payment\Infrastructure\Persistence\EloquentIdempotencyStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentIdempotencyStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_acquires_the_lock_for_a_new_key(): void
    {
        $store = new EloquentIdempotencyStore();

        $attempt = $store->acquireLock('idem-key-1', 'fingerprint-a');

        $this->assertTrue($attempt->acquired);
        $this->assertNull($attempt->existing);
        $this->assertDatabaseHas('idempotency_keys', [
            'key' => 'idem-key-1',
            'fingerprint' => 'fingerprint-a',
            'status' => IdempotencyRecordStatus::LOCKED->value,
        ]);
    }

    public function test_a_second_acquire_for_the_same_key_returns_a_conflict_with_the_existing_record(): void
    {
        $store = new EloquentIdempotencyStore();
        $store->acquireLock('idem-key-1', 'fingerprint-a');

        $attempt = $store->acquireLock('idem-key-1', 'fingerprint-a');

        $this->assertFalse($attempt->acquired);
        $this->assertNotNull($attempt->existing);
        $this->assertSame('idem-key-1', $attempt->existing->key);
        $this->assertSame('fingerprint-a', $attempt->existing->fingerprint);
        $this->assertSame(IdempotencyRecordStatus::LOCKED, $attempt->existing->status);
    }

    public function test_mark_completed_stores_the_response_to_replay(): void
    {
        $store = new EloquentIdempotencyStore();
        $store->acquireLock('idem-key-1', 'fingerprint-a');

        $store->markCompleted('idem-key-1', 201, '{"id":"pay_1"}');

        $this->assertDatabaseHas('idempotency_keys', [
            'key' => 'idem-key-1',
            'status' => IdempotencyRecordStatus::COMPLETED->value,
            'response_code' => 201,
            'response_body' => '{"id":"pay_1"}',
        ]);
    }

    public function test_mark_failed_stores_the_response_to_replay(): void
    {
        $store = new EloquentIdempotencyStore();
        $store->acquireLock('idem-key-1', 'fingerprint-a');

        $store->markFailed('idem-key-1', 422, '{"error":"declined"}');

        $this->assertDatabaseHas('idempotency_keys', [
            'key' => 'idem-key-1',
            'status' => IdempotencyRecordStatus::FAILED->value,
            'response_code' => 422,
            'response_body' => '{"error":"declined"}',
        ]);
    }
}

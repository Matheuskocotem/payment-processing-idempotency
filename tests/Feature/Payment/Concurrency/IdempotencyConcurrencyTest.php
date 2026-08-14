<?php

declare(strict_types=1);

namespace Tests\Feature\Payment\Concurrency;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fires genuinely concurrent HTTP requests (via curl_multi, real OS-level
 * parallelism, not sequential calls in a loop) at the live nginx/php-fpm
 * stack to prove the UNIQUE-constraint lock in EloquentIdempotencyStore
 * actually serializes racing requests end to end.
 *
 * Requires `docker compose up -d app` (nginx+php-fpm+mysql+redis) running
 * first — reached over the docker-compose network at http://app. Skips
 * itself if that service isn't reachable, so `vendor/bin/phpunit` alone
 * (without the stack up) doesn't fail the whole suite.
 */
final class IdempotencyConcurrencyTest extends TestCase
{
    private const APP_URL = 'http://app';

    protected function tearDown(): void
    {
        DB::table('payment_events')->whereIn('payment_id', function ($query) {
            $query->select('id')->from('payments')->where('customer_id', 'like', 'cus_concurrency_%');
        })->delete();
        DB::table('payments')->where('customer_id', 'like', 'cus_concurrency_%')->delete();
        DB::table('idempotency_keys')->where('key', 'like', 'concurrency-test-%')->delete();

        parent::tearDown();
    }

    public function test_n_concurrent_requests_with_the_same_idempotency_key_create_exactly_one_payment(): void
    {
        if (! $this->appIsReachable()) {
            $this->markTestSkipped(
                'Requires the "app" service reachable at '.self::APP_URL
                .' — run `docker compose up -d app` first.',
            );
        }

        $key = 'concurrency-test-'.Str::uuid();
        $customerId = 'cus_concurrency_'.Str::random(8);
        $body = json_encode([
            'customer_id' => $customerId,
            'amount_cents' => 777,
            'currency' => 'BRL',
        ], JSON_THROW_ON_ERROR);

        $concurrentRequests = 15;
        $responses = $this->fireConcurrentRequests($key, $body, $concurrentRequests);

        $this->assertCount($concurrentRequests, $responses);

        $paymentIds = [];
        foreach ($responses as [$status, $responseBody]) {
            $this->assertContains($status, [201, 409], "Unexpected status {$status}: {$responseBody}");

            if ($status === 201) {
                $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
                $paymentIds[] = $decoded['id'];
            }
        }

        $this->assertNotEmpty($paymentIds, 'No concurrent request succeeded.');
        $this->assertCount(1, array_unique($paymentIds), 'More than one distinct payment id was returned under concurrency.');

        $this->assertSame(
            1,
            DB::table('payments')->where('customer_id', $customerId)->count(),
            'Expected exactly one payment row despite N concurrent requests.',
        );
        $this->assertSame(1, DB::table('idempotency_keys')->where('key', $key)->count());
    }

    /**
     * @return list<array{0: int, 1: string}>
     */
    private function fireConcurrentRequests(string $key, string $body, int $count): array
    {
        $multiHandle = curl_multi_init();
        $handles = [];

        for ($i = 0; $i < $count; $i++) {
            $ch = curl_init(self::APP_URL.'/api/payments');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Idempotency-Key: '.$key,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);
            curl_multi_add_handle($multiHandle, $ch);
            $handles[] = $ch;
        }

        $running = null;
        do {
            $status = curl_multi_exec($multiHandle, $running);
            if ($running > 0) {
                curl_multi_select($multiHandle, 0.1);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $responses = [];
        foreach ($handles as $ch) {
            $responses[] = [
                curl_getinfo($ch, CURLINFO_HTTP_CODE),
                (string) curl_multi_getcontent($ch),
            ];
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }
        curl_multi_close($multiHandle);

        return $responses;
    }

    private function appIsReachable(): bool
    {
        // Docker's network bridge can be slow to attach right after a
        // one-off `docker compose run` container starts, so the very first
        // outbound connection occasionally cold-start-times-out — retry a
        // couple of times before concluding the service is actually down.
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init(self::APP_URL.'/api/payments');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => '{}',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            curl_exec($ch);
            $reachable = curl_errno($ch) === 0;
            curl_close($ch);

            if ($reachable) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Http\Middleware;

use App\Payment\Domain\Port\Idempotency\IdempotencyRecordStatus;
use App\Payment\Domain\Port\IdempotencyStore;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Enforces client→API idempotency on the `Idempotency-Key` header.
 *
 * Flow: try to atomically INSERT a LOCKED record for (key, fingerprint) —
 * the UNIQUE constraint on idempotency_keys.key is the anti-race lock, so
 * concurrent requests with the same key never both proceed past this point.
 * The loser gets the winner's outcome by inspecting the existing record.
 *
 * Known MVP limitation: a LOCKED record whose owning request crashed before
 * reaching markCompleted()/markFailed() stays LOCKED forever (no TTL/stale
 * lock recovery here) — every retry gets 409 until it's cleared manually.
 */
final class IdempotencyMiddleware
{
    private const HEADER = 'Idempotency-Key';

    public function __construct(
        private readonly IdempotencyStore $store,
    ) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $key = $request->header(self::HEADER);

        if (! is_string($key) || $key === '') {
            return $this->jsonError(
                SymfonyResponse::HTTP_BAD_REQUEST,
                'idempotency_key_required',
                sprintf('The "%s" header is required.', self::HEADER),
            );
        }

        try {
            $fingerprint = $this->fingerprint($request);
        } catch (JsonException) {
            return $this->jsonError(
                SymfonyResponse::HTTP_BAD_REQUEST,
                'invalid_json_body',
                'The request body must be valid JSON.',
            );
        }

        $attempt = $this->store->acquireLock($key, $fingerprint);

        if (! $attempt->acquired) {
            $existing = $attempt->existing;

            if ($existing->status === IdempotencyRecordStatus::LOCKED) {
                return $this->jsonError(
                    SymfonyResponse::HTTP_CONFLICT,
                    'idempotency_key_locked',
                    sprintf('A request with "%s: %s" is already being processed.', self::HEADER, $key),
                );
            }

            if (! $existing->hasFingerprint($fingerprint)) {
                return $this->jsonError(
                    SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY,
                    'idempotency_key_reused',
                    sprintf('"%s: %s" was already used with a different request body.', self::HEADER, $key),
                );
            }

            /** @var int $responseCode */
            $responseCode = $existing->responseCode;
            /** @var string $responseBody */
            $responseBody = $existing->responseBody;

            return response($responseBody, $responseCode)
                ->header('Content-Type', 'application/json')
                ->header('Idempotent-Replay', 'true');
        }

        $response = $next($request);

        if ($response->getStatusCode() < 400) {
            $this->store->markCompleted($key, $response->getStatusCode(), (string) $response->getContent());
        } else {
            $this->store->markFailed($key, $response->getStatusCode(), (string) $response->getContent());
        }

        return $response;
    }

    /**
     * @throws JsonException
     */
    private function fingerprint(Request $request): string
    {
        $raw = $request->getContent();
        $decoded = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $canonical = json_encode(
            $this->canonicalize($decoded),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return hash('sha256', $canonical);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->canonicalize(...), $value);
    }

    private function jsonError(int $status, string $error, string $message): JsonResponse
    {
        return response()->json(['error' => $error, 'message' => $message], $status);
    }
}

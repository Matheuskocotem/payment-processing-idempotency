<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence;

use App\Payment\Domain\Port\Idempotency\IdempotencyLockAttempt;
use App\Payment\Domain\Port\Idempotency\IdempotencyRecord;
use App\Payment\Domain\Port\Idempotency\IdempotencyRecordStatus;
use App\Payment\Domain\Port\IdempotencyStore;
use App\Payment\Infrastructure\Persistence\Eloquent\IdempotencyKeyModel;
use Illuminate\Database\QueryException;

final class EloquentIdempotencyStore implements IdempotencyStore
{
    private const MYSQL_DUPLICATE_ENTRY = 1062;

    public function acquireLock(string $key, string $fingerprint): IdempotencyLockAttempt
    {
        try {
            IdempotencyKeyModel::query()->create([
                'key' => $key,
                'fingerprint' => $fingerprint,
                'status' => IdempotencyRecordStatus::LOCKED->value,
            ]);

            return IdempotencyLockAttempt::acquired();
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== self::MYSQL_DUPLICATE_ENTRY) {
                throw $e;
            }

            $existing = IdempotencyKeyModel::query()->where('key', $key)->firstOrFail();

            return IdempotencyLockAttempt::conflict($this->toRecord($existing));
        }
    }

    public function markCompleted(string $key, int $responseCode, string $responseBody): void
    {
        $this->markResolved($key, IdempotencyRecordStatus::COMPLETED, $responseCode, $responseBody);
    }

    public function markFailed(string $key, int $responseCode, string $responseBody): void
    {
        $this->markResolved($key, IdempotencyRecordStatus::FAILED, $responseCode, $responseBody);
    }

    private function markResolved(
        string $key,
        IdempotencyRecordStatus $status,
        int $responseCode,
        string $responseBody,
    ): void {
        IdempotencyKeyModel::query()->where('key', $key)->update([
            'status' => $status->value,
            'response_code' => $responseCode,
            'response_body' => $responseBody,
        ]);
    }

    private function toRecord(IdempotencyKeyModel $model): IdempotencyRecord
    {
        return new IdempotencyRecord(
            key: $model->key,
            fingerprint: $model->fingerprint,
            status: IdempotencyRecordStatus::from($model->status),
            responseCode: $model->response_code,
            responseBody: $model->response_body,
        );
    }
}

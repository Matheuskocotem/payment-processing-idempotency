<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $fingerprint
 * @property string $status
 * @property int|null $response_code
 * @property string|null $response_body
 */
final class IdempotencyKeyModel extends Model
{
    protected $table = 'idempotency_keys';

    protected $fillable = [
        'key',
        'fingerprint',
        'status',
        'response_code',
        'response_body',
    ];

    protected $casts = [
        'response_code' => 'integer',
    ];
}

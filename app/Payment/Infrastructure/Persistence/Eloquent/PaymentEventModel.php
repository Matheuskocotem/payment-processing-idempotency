<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only event log — rows are never updated, so there is no updated_at.
 *
 * @property int $id
 * @property string $payment_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon $created_at
 */
final class PaymentEventModel extends Model
{
    protected $table = 'payment_events';

    public $timestamps = false;

    protected $fillable = [
        'payment_id',
        'type',
        'payload',
        'occurred_at',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];
}

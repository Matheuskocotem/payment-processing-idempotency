<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $customer_id
 * @property int $amount_cents
 * @property string $currency
 * @property string $status
 * @property string|null $provider_ref
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class PaymentModel extends Model
{
    protected $table = 'payments';

    protected $keyType = 'string';

    public $incrementing = false;

    // Row timestamps mirror the domain clock (Payment::createdAt/updatedAt);
    // Eloquent must never overwrite them with request time.
    public $timestamps = false;

    protected $casts = [
        'amount_cents' => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEventModel::class, 'payment_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence;

use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Port\PaymentRepository;
use App\Payment\Domain\ValueObject\Money;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Payment\Infrastructure\Persistence\Eloquent\PaymentEventModel;
use App\Payment\Infrastructure\Persistence\Eloquent\PaymentModel;
use Illuminate\Support\Facades\DB;

final class EloquentPaymentRepository implements PaymentRepository
{
    public function save(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $model = PaymentModel::query()->find($payment->id) ?? new PaymentModel();
            $model->id = $payment->id;
            $model->customer_id = $payment->customerId;
            $model->amount_cents = $payment->money->amountCents;
            $model->currency = $payment->money->currency;
            $model->status = $payment->status()->value;
            $model->provider_ref = $payment->providerRef();
            $model->created_at = $payment->createdAt;
            $model->updated_at = $payment->updatedAt();
            $model->save();

            foreach ($payment->releaseEvents() as $event) {
                PaymentEventModel::query()->create([
                    'payment_id' => $payment->id,
                    'type' => $event['type'],
                    'payload' => $event['payload'],
                    'occurred_at' => $event['at'],
                    'created_at' => $event['at'],
                ]);
            }
        });
    }

    public function findById(string $id): ?Payment
    {
        $model = PaymentModel::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    private function toDomain(PaymentModel $model): Payment
    {
        return new Payment(
            id: $model->id,
            customerId: $model->customer_id,
            money: new Money($model->amount_cents, $model->currency),
            status: PaymentStatus::from($model->status),
            providerRef: $model->provider_ref,
            createdAt: $model->created_at,
            updatedAt: $model->updated_at,
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Payment\Infrastructure;

use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\ValueObject\Money;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Payment\Infrastructure\Persistence\Eloquent\PaymentEventModel;
use App\Payment\Infrastructure\Persistence\EloquentPaymentRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class EloquentPaymentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_a_new_payment_and_its_domain_events(): void
    {
        $repository = new EloquentPaymentRepository();
        $id = (string) Uuid::uuid4();
        $now = new DateTimeImmutable('2026-08-14T10:00:00Z');
        $payment = Payment::initiate($id, 'cus_1', new Money(1_000, 'BRL'), $now);

        $repository->save($payment);

        $this->assertDatabaseHas('payments', [
            'id' => $id,
            'customer_id' => 'cus_1',
            'amount_cents' => 1_000,
            'currency' => 'BRL',
            'status' => PaymentStatus::PENDING->value,
            'provider_ref' => null,
        ]);
        $this->assertSame(1, PaymentEventModel::query()->where('payment_id', $id)->count());
        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $id,
            'type' => 'payment.initiated',
        ]);
    }

    public function test_save_is_idempotent_upsert_and_only_persists_newly_released_events(): void
    {
        $repository = new EloquentPaymentRepository();
        $id = (string) Uuid::uuid4();
        $now = new DateTimeImmutable('2026-08-14T10:00:00Z');
        $payment = Payment::initiate($id, 'cus_1', new Money(1_000, 'BRL'), $now);
        $repository->save($payment);

        $payment->transitionTo(PaymentStatus::AUTHORIZED, $now->modify('+1 minute'), providerRef: 'psp_ref');
        $repository->save($payment);

        $this->assertSame(1, \App\Payment\Infrastructure\Persistence\Eloquent\PaymentModel::query()->where('id', $id)->count());
        $this->assertDatabaseHas('payments', [
            'id' => $id,
            'status' => PaymentStatus::AUTHORIZED->value,
            'provider_ref' => 'psp_ref',
        ]);
        $this->assertSame(2, PaymentEventModel::query()->where('payment_id', $id)->count());
    }

    public function test_find_by_id_reconstructs_the_domain_entity(): void
    {
        $repository = new EloquentPaymentRepository();
        $id = (string) Uuid::uuid4();
        $now = new DateTimeImmutable('2026-08-14T10:00:00Z');
        $repository->save(Payment::initiate($id, 'cus_1', new Money(2_500, 'USD'), $now));

        $found = $repository->findById($id);

        $this->assertNotNull($found);
        $this->assertSame($id, $found->id);
        $this->assertSame('cus_1', $found->customerId);
        $this->assertTrue($found->money->equals(new Money(2_500, 'USD')));
        $this->assertSame(PaymentStatus::PENDING, $found->status());
    }

    public function test_find_by_id_returns_null_when_missing(): void
    {
        $repository = new EloquentPaymentRepository();

        $this->assertNull($repository->findById((string) Uuid::uuid4()));
    }
}

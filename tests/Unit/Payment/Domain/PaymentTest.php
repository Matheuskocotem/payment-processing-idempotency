<?php

declare(strict_types=1);

namespace Tests\Unit\Payment\Domain;

use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Exception\InvalidTransitionException;
use App\Payment\Domain\ValueObject\Money;
use App\Payment\Domain\ValueObject\PaymentStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    public function test_initiate_creates_a_pending_payment_and_records_an_event(): void
    {
        $now = new DateTimeImmutable('2026-08-14T10:00:00Z');
        $money = new Money(5_000, 'BRL');

        $payment = Payment::initiate('pay_1', 'cus_1', $money, $now);

        $this->assertSame(PaymentStatus::PENDING, $payment->status());
        $this->assertNull($payment->providerRef());
        $this->assertSame($now, $payment->createdAt);
        $this->assertSame($now, $payment->updatedAt());

        $events = $payment->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertSame('payment.initiated', $events[0]['type']);
        $this->assertSame(5_000, $events[0]['payload']['amount_cents']);
        $this->assertSame('BRL', $events[0]['payload']['currency']);
    }

    public function test_release_events_drains_the_buffer(): void
    {
        $now = new DateTimeImmutable();
        $payment = Payment::initiate('pay_1', 'cus_1', new Money(1_000, 'BRL'), $now);

        $payment->releaseEvents();

        $this->assertSame([], $payment->releaseEvents());
    }

    public function test_transition_to_moves_status_updates_timestamp_and_records_event(): void
    {
        $createdAt = new DateTimeImmutable('2026-08-14T10:00:00Z');
        $transitionedAt = new DateTimeImmutable('2026-08-14T10:05:00Z');
        $payment = Payment::initiate('pay_1', 'cus_1', new Money(1_000, 'BRL'), $createdAt);
        $payment->releaseEvents();

        $payment->transitionTo(
            PaymentStatus::AUTHORIZED,
            $transitionedAt,
            ['provider' => 'fake'],
            providerRef: 'psp_ref_123',
        );

        $this->assertSame(PaymentStatus::AUTHORIZED, $payment->status());
        $this->assertSame('psp_ref_123', $payment->providerRef());
        $this->assertSame($transitionedAt, $payment->updatedAt());

        $events = $payment->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertSame('payment.authorized', $events[0]['type']);
        $this->assertSame(['provider' => 'fake'], $events[0]['payload']);
    }

    public function test_transition_to_preserves_provider_ref_when_not_given(): void
    {
        $now = new DateTimeImmutable();
        $payment = Payment::initiate('pay_1', 'cus_1', new Money(1_000, 'BRL'), $now);
        $payment->transitionTo(PaymentStatus::AUTHORIZED, $now, providerRef: 'psp_ref_123');

        $payment->transitionTo(PaymentStatus::CAPTURED, $now);

        $this->assertSame('psp_ref_123', $payment->providerRef());
    }

    public function test_invalid_transition_throws_and_leaves_state_unchanged(): void
    {
        $now = new DateTimeImmutable();
        $payment = Payment::initiate('pay_1', 'cus_1', new Money(1_000, 'BRL'), $now);

        try {
            $payment->transitionTo(PaymentStatus::CAPTURED, $now);
            $this->fail('Expected InvalidTransitionException was not thrown.');
        } catch (InvalidTransitionException $e) {
            $this->assertSame('Invalid payment transition: PENDING → CAPTURED.', $e->getMessage());
        }

        $this->assertSame(PaymentStatus::PENDING, $payment->status());
    }

    public function test_invalid_transition_from_a_terminal_state_throws(): void
    {
        $now = new DateTimeImmutable();
        $payment = Payment::initiate('pay_1', 'cus_1', new Money(1_000, 'BRL'), $now);
        $payment->transitionTo(PaymentStatus::FAILED, $now);

        $this->expectException(InvalidTransitionException::class);

        $payment->transitionTo(PaymentStatus::AUTHORIZED, $now);
    }
}

<?php

declare(strict_types=1);

namespace App\Payment\Domain\Entity;

use App\Payment\Domain\Exception\InvalidTransitionException;
use App\Payment\Domain\ValueObject\Money;
use App\Payment\Domain\ValueObject\PaymentStatus;
use DateTimeImmutable;

final class Payment
{
    /** @var list<array{type: string, payload: array<string, mixed>, at: DateTimeImmutable}> */
    private array $recordedEvents = [];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $customerId,
        public readonly Money $money,
        private PaymentStatus $status,
        private ?string $providerRef,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function initiate(
        string $id,
        string $customerId,
        Money $money,
        DateTimeImmutable $now,
    ): self {
        $payment = new self(
            id: $id,
            customerId: $customerId,
            money: $money,
            status: PaymentStatus::PENDING,
            providerRef: null,
            createdAt: $now,
            updatedAt: $now,
        );

        $payment->record('payment.initiated', [
            'amount_cents' => $money->amountCents,
            'currency' => $money->currency,
        ], $now);

        return $payment;
    }

    public function status(): PaymentStatus
    {
        return $this->status;
    }

    public function providerRef(): ?string
    {
        return $this->providerRef;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    public function transitionTo(
        PaymentStatus $target,
        DateTimeImmutable $now,
        array $eventPayload = [],
        ?string $providerRef = null,
    ): void {
        if (! $this->status->canTransitionTo($target)) {
            throw InvalidTransitionException::between($this->status, $target);
        }

        $this->status = $target;
        $this->updatedAt = $now;

        if ($providerRef !== null) {
            $this->providerRef = $providerRef;
        }

        $this->record('payment.'.strtolower($target->value), $eventPayload, $now);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(string $type, array $payload, DateTimeImmutable $at): void
    {
        $this->recordedEvents[] = [
            'type' => $type,
            'payload' => $payload,
            'at' => $at,
        ];
    }

    /**
     * @return list<array{type: string, payload: array<string, mixed>, at: DateTimeImmutable}>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}

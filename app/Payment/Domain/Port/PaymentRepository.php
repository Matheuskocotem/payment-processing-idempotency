<?php

declare(strict_types=1);

namespace App\Payment\Domain\Port;

use App\Payment\Domain\Entity\Payment;

/**
 * Port for persisting and retrieving the Payment aggregate. Implementations
 * in Infrastructure are also responsible for persisting the domain events
 * released via Payment::releaseEvents() when save() is called.
 */
interface PaymentRepository
{
    public function save(Payment $payment): void;

    public function findById(string $id): ?Payment;
}

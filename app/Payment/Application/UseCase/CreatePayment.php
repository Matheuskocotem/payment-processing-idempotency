<?php

declare(strict_types=1);

namespace App\Payment\Application\UseCase;

use App\Payment\Application\DTO\CreatePaymentInput;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Exception\GatewayUnknownOutcomeException;
use App\Payment\Domain\Port\Gateway\GatewayChargeRequest;
use App\Payment\Domain\Port\PaymentGateway;
use App\Payment\Domain\Port\PaymentRepository;
use App\Payment\Domain\ValueObject\Money;
use App\Payment\Domain\ValueObject\PaymentStatus;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

/**
 * Orchestrates creating a payment and charging it through the PSP. Depends
 * only on Domain ports — never on Eloquent, HTTP, or the queue — so it can
 * be unit tested against in-memory fakes of PaymentRepository/PaymentGateway.
 */
final class CreatePayment
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly PaymentGateway $gateway,
    ) {}

    public function handle(CreatePaymentInput $input): Payment
    {
        $payment = Payment::initiate(
            id: Uuid::uuid4()->toString(),
            customerId: $input->customerId,
            money: new Money($input->amountCents, $input->currency),
            now: new DateTimeImmutable(),
        );
        $this->payments->save($payment);

        try {
            $result = $this->gateway->charge(new GatewayChargeRequest(
                idempotencyKey: $payment->id,
                paymentId: $payment->id,
                customerId: $payment->customerId,
                money: $payment->money,
            ));
        } catch (GatewayUnknownOutcomeException) {
            // Outcome unknown (timeout / lost response): leave the payment PENDING
            // rather than guess. Reconciliation converges it once the PSP's true
            // state is known.
            return $payment;
        }

        if ($result->isSucceeded()) {
            $payment->transitionTo(PaymentStatus::AUTHORIZED, new DateTimeImmutable(), providerRef: $result->providerRef);
            $payment->transitionTo(PaymentStatus::CAPTURED, new DateTimeImmutable());
        } else {
            $payment->transitionTo(PaymentStatus::FAILED, new DateTimeImmutable(), [
                'reason' => $result->declineReason,
            ]);
        }

        $this->payments->save($payment);

        return $payment;
    }
}

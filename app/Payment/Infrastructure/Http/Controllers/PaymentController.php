<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Payment\Application\DTO\CreatePaymentInput;
use App\Payment\Application\UseCase\CreatePayment;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\ValueObject\PaymentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly CreatePayment $createPayment,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'string'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        try {
            $payment = $this->createPayment->handle(new CreatePaymentInput(
                customerId: $validated['customer_id'],
                amountCents: $validated['amount_cents'],
                currency: strtoupper($validated['currency']),
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 'invalid_payment', 'message' => $e->getMessage()], 422);
        }

        return response()->json($this->present($payment), $this->statusFor($payment));
    }

    private function statusFor(Payment $payment): int
    {
        return match ($payment->status()) {
            PaymentStatus::AUTHORIZED, PaymentStatus::CAPTURED => 201,
            PaymentStatus::FAILED => 422,
            PaymentStatus::PENDING => 202,
            default => 200,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'customer_id' => $payment->customerId,
            'amount_cents' => $payment->money->amountCents,
            'currency' => $payment->money->currency,
            'status' => $payment->status()->value,
            'provider_ref' => $payment->providerRef(),
            'created_at' => $payment->createdAt->format(DATE_ATOM),
            'updated_at' => $payment->updatedAt()->format(DATE_ATOM),
        ];
    }
}

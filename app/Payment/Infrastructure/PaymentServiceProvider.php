<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure;

use App\Payment\Domain\Port\IdempotencyStore;
use App\Payment\Domain\Port\PaymentGateway;
use App\Payment\Domain\Port\PaymentRepository;
use App\Payment\Infrastructure\Gateway\FakePaymentGateway;
use App\Payment\Infrastructure\Persistence\EloquentIdempotencyStore;
use App\Payment\Infrastructure\Persistence\EloquentPaymentRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Wires Domain ports to their Infrastructure adapters. Swapping the fake PSP
 * for a real one (e.g. Stripe) means changing only the PaymentGateway
 * binding here — Domain and Application stay untouched.
 */
final class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentRepository::class, EloquentPaymentRepository::class);
        $this->app->bind(IdempotencyStore::class, EloquentIdempotencyStore::class);

        // Singleton: fault injection configured on the resolved instance (e.g.
        // in tests) must be visible to the same instance the controller uses.
        $this->app->singleton(PaymentGateway::class, FakePaymentGateway::class);
    }
}

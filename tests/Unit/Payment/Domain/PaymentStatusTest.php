<?php

declare(strict_types=1);

namespace Tests\Unit\Payment\Domain;

use App\Payment\Domain\ValueObject\PaymentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaymentStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{PaymentStatus, PaymentStatus}>
     */
    public static function allowedTransitions(): iterable
    {
        yield 'pending to authorized' => [PaymentStatus::PENDING, PaymentStatus::AUTHORIZED];
        yield 'pending to failed' => [PaymentStatus::PENDING, PaymentStatus::FAILED];
        yield 'pending to canceled' => [PaymentStatus::PENDING, PaymentStatus::CANCELED];
        yield 'authorized to captured' => [PaymentStatus::AUTHORIZED, PaymentStatus::CAPTURED];
        yield 'authorized to failed' => [PaymentStatus::AUTHORIZED, PaymentStatus::FAILED];
        yield 'authorized to refunded' => [PaymentStatus::AUTHORIZED, PaymentStatus::REFUNDED];
        yield 'captured to settled' => [PaymentStatus::CAPTURED, PaymentStatus::SETTLED];
        yield 'captured to refunded' => [PaymentStatus::CAPTURED, PaymentStatus::REFUNDED];
        yield 'settled to refunded' => [PaymentStatus::SETTLED, PaymentStatus::REFUNDED];
    }

    #[DataProvider('allowedTransitions')]
    public function test_it_allows_valid_transitions(PaymentStatus $from, PaymentStatus $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
    }

    /**
     * @return iterable<string, array{PaymentStatus, PaymentStatus}>
     */
    public static function forbiddenTransitions(): iterable
    {
        yield 'pending to captured (skips authorized)' => [PaymentStatus::PENDING, PaymentStatus::CAPTURED];
        yield 'pending to settled' => [PaymentStatus::PENDING, PaymentStatus::SETTLED];
        yield 'authorized to settled (skips captured)' => [PaymentStatus::AUTHORIZED, PaymentStatus::SETTLED];
        yield 'captured to authorized (backwards)' => [PaymentStatus::CAPTURED, PaymentStatus::AUTHORIZED];
        yield 'settled to captured (backwards)' => [PaymentStatus::SETTLED, PaymentStatus::CAPTURED];
        yield 'refunded to anything' => [PaymentStatus::REFUNDED, PaymentStatus::SETTLED];
        yield 'failed to anything' => [PaymentStatus::FAILED, PaymentStatus::PENDING];
        yield 'canceled to anything' => [PaymentStatus::CANCELED, PaymentStatus::PENDING];
    }

    #[DataProvider('forbiddenTransitions')]
    public function test_it_forbids_invalid_transitions(PaymentStatus $from, PaymentStatus $to): void
    {
        $this->assertFalse($from->canTransitionTo($to));
    }

    /**
     * @return iterable<string, array{PaymentStatus, bool}>
     */
    public static function terminalStates(): iterable
    {
        yield 'pending is not terminal' => [PaymentStatus::PENDING, false];
        yield 'authorized is not terminal' => [PaymentStatus::AUTHORIZED, false];
        yield 'captured is not terminal' => [PaymentStatus::CAPTURED, false];
        yield 'settled is not terminal' => [PaymentStatus::SETTLED, false];
        yield 'refunded is terminal' => [PaymentStatus::REFUNDED, true];
        yield 'failed is terminal' => [PaymentStatus::FAILED, true];
        yield 'canceled is terminal' => [PaymentStatus::CANCELED, true];
    }

    #[DataProvider('terminalStates')]
    public function test_is_terminal(PaymentStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isTerminal());
    }
}

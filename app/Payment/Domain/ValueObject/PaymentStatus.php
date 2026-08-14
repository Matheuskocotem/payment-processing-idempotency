<?php

declare(strict_types=1);

namespace App\Payment\Domain\ValueObject;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case AUTHORIZED = 'AUTHORIZED';
    case CAPTURED = 'CAPTURED';
    case SETTLED = 'SETTLED';
    case REFUNDED = 'REFUNDED';
    case FAILED = 'FAILED';
    case CANCELED = 'CANCELED';

    /**
     * @return array<string, list<PaymentStatus>>
     */
    private static function transitions(): array
    {
        return [
            self::PENDING->value => [self::AUTHORIZED, self::FAILED, self::CANCELED],
            self::AUTHORIZED->value => [self::CAPTURED, self::FAILED, self::REFUNDED],
            self::CAPTURED->value => [self::SETTLED, self::REFUNDED],
            self::SETTLED->value => [self::REFUNDED],
            self::REFUNDED->value => [],
            self::FAILED->value => [],
            self::CANCELED->value => [],
        ];
    }

    public function canTransitionTo(PaymentStatus $target): bool
    {
        foreach (self::transitions()[$this->value] as $allowed) {
            if ($allowed === $target) {
                return true;
            }
        }
        return false;
    }

    public function isTerminal(): bool
    {
        return self::transitions()[$this->value] === [];
    }
}
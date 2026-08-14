<?php

declare(strict_types=1);

namespace App\Payment\Domain\Exception;

use App\Payment\Domain\ValueObject\PaymentStatus;
use DomainException;

final class InvalidTransitionException extends DomainException
{
    public static function between(PaymentStatus $from, PaymentStatus $to): self
    {
        return new self(sprintf(
            'Invalid payment transition: %s → %s.',
            $from->value,
            $to->value
        ));
    }
}

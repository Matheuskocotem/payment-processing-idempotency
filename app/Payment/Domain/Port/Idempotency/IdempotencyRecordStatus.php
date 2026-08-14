<?php

declare(strict_types=1);

namespace App\Payment\Domain\Port\Idempotency;

enum IdempotencyRecordStatus: string
{
    case LOCKED = 'LOCKED';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
}

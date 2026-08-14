<?php

declare(strict_types=1);

namespace App\Payment\Domain\Port\Gateway;

enum GatewayChargeOutcome: string
{
    case SUCCEEDED = 'SUCCEEDED';
    case DECLINED = 'DECLINED';
}

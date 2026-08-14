<?php

declare(strict_types=1);

namespace App\Payment\Domain\Port;

use App\Payment\Domain\Exception\GatewayResponseLostException;
use App\Payment\Domain\Exception\GatewayTimeoutException;
use App\Payment\Domain\Port\Gateway\GatewayChargeRequest;
use App\Payment\Domain\Port\Gateway\GatewayChargeResult;

/**
 * Port to the payment service provider (PSP). Implementations live in
 * Infrastructure (e.g. a fake for the MVP, a Stripe adapter later) and are
 * swappable without touching Domain or Application.
 */
interface PaymentGateway
{
    /**
     * @throws GatewayTimeoutException     when the PSP didn't respond in time
     * @throws GatewayResponseLostException when the response was lost after being sent
     */
    public function charge(GatewayChargeRequest $request): GatewayChargeResult;
}

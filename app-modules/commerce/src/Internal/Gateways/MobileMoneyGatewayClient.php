<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Gateways;

interface MobileMoneyGatewayClient
{
    /** @return 'success'|'pending'|'failed' */
    public function queryStatus(string $reference): string;
}

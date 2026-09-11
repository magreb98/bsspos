<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Enums;

enum PaymentMethod: string
{
    case Cash        = 'cash';
    case MobileMoney = 'mobile_money';
}

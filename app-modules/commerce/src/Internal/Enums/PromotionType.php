<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Enums;

enum PromotionType: string
{
    case Percent     = 'percent';
    case FixedAmount = 'fixed_amount';
}

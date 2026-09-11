<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Enums;

enum PromotionScope: string
{
    case Product = 'product';
    case Family  = 'family';
}

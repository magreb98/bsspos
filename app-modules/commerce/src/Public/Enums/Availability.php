<?php

declare(strict_types=1);

namespace Modules\Commerce\Public\Enums;

enum Availability: string
{
    case Available = 'available';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';
}

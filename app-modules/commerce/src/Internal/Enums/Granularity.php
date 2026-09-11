<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Enums;

enum Granularity: string
{
    case Quantity = 'quantity';
    case Variant  = 'variant';
    case Serial   = 'serial';
    case Batch    = 'batch';
    case Service  = 'service';
}

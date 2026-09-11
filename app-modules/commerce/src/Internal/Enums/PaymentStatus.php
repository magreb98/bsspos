<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Enums;

enum PaymentStatus: string
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Failed    = 'failed';
}

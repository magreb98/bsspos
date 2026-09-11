<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Enums;

enum TransferStatus: string
{
    case Pending    = 'pending';
    case InTransit  = 'in_transit';
    case Received   = 'received';
    case Cancelled  = 'cancelled';
}

<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Enums;

enum SupplierOrderStatus: string
{
    case Draft    = 'draft';
    case Sent     = 'sent';
    case Received = 'received';
}

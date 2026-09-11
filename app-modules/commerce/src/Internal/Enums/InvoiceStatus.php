<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Enums;

enum InvoiceStatus: string
{
    case Draft         = 'draft';
    case Sent          = 'sent';
    case PartiallyPaid = 'partially_paid';
    case Paid          = 'paid';
    case Overdue       = 'overdue';
}

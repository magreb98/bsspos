<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Enums;

enum SaleState: string
{
    case Draft     = 'draft';
    case Quote     = 'quote';
    case Confirmed = 'confirmed';
    case Abandoned = 'abandoned';
}

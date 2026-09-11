<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Enums;

enum SessionState: string
{
    case Open   = 'open';
    case Closed = 'closed';
}

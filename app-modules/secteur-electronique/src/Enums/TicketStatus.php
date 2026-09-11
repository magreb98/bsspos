<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Enums;

enum TicketStatus: string
{
    case Open     = 'open';
    case InRepair = 'in_repair';
    case Closed   = 'closed';
}

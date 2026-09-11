<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Enums;

enum SerialStatus: string
{
    case Available = 'available';
    case Sold      = 'sold';
    case InService = 'in_service';
}

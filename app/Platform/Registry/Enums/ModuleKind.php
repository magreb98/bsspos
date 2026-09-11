<?php

declare(strict_types=1);

namespace App\Platform\Registry\Enums;

enum ModuleKind: string
{
    case Domain = 'domain';
    case Sector = 'sector';
}

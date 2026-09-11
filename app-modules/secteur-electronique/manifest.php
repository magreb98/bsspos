<?php

declare(strict_types=1);

use App\Platform\Registry\DomainModule;
use App\Platform\Registry\Enums\ModuleKind;

return new DomainModule(
    id: 'secteur-electronique',
    kind: ModuleKind::Sector,
    label: 'Secteur Électronique',
    migrations: [__DIR__ . '/database/migrations'],
);

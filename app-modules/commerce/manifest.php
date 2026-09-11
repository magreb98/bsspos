<?php

declare(strict_types=1);

use App\Platform\Registry\DomainModule;
use App\Platform\Registry\Enums\ModuleKind;

return new DomainModule(
    id: 'commerce',
    kind: ModuleKind::Domain,
    label: 'Commerce',
    migrations: [__DIR__ . '/database/migrations'],
);

<?php

declare(strict_types=1);

namespace App\Platform\Registry;

use App\Platform\Registry\Enums\ModuleKind;

final readonly class DomainModule
{
    /**
     * @param list<string>|null $permissions
     * @param list<string>|null $entities
     * @param list<string>|null $documents
     * @param list<string>|null $routes
     * @param list<string>|null $migrations
     */
    public function __construct(
        public string $id,
        public ModuleKind $kind,
        public string $label,
        public ?array $permissions = null,
        public ?array $entities = null,
        public ?array $documents = null,
        public ?array $routes = null,
        public ?array $migrations = null,
    ) {
    }
}

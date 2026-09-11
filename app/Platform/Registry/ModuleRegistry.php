<?php

declare(strict_types=1);

namespace App\Platform\Registry;

use App\Platform\Registry\Enums\ModuleKind;

final class ModuleRegistry
{
    /** @var array<string, DomainModule> */
    private array $modules = [];

    public function register(DomainModule $module): void
    {
        $this->modules[$module->id] = $module;
    }

    public function find(string $id): ?DomainModule
    {
        return $this->modules[$id] ?? null;
    }

    /** @return array<string, DomainModule> */
    public function all(): array
    {
        return $this->modules;
    }

    /** @return list<DomainModule> */
    public function ofKind(ModuleKind $kind): array
    {
        return array_values(
            array_filter($this->modules, fn (DomainModule $m) => $m->kind === $kind)
        );
    }

    public function loadFromFile(string $path): void
    {
        $module = require $path;

        if ($module instanceof DomainModule) {
            $this->register($module);
        }
    }
}

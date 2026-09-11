<?php

declare(strict_types=1);

/**
 * Unit tests for ModuleRegistry — Task 0.6: Registry, manifests, translation and socle:verifier
 *
 * Decisions:
 *   D1 — ModuleKind: string-backed enum with Domain and Sector cases
 *   D2 — DomainModule: readonly value object with id, kind, label
 *   D3 — ModuleRegistry: mutable singleton with register(), find(), all(), ofKind(), loadFromFile()
 */

use App\Platform\Registry\DomainModule;
use App\Platform\Registry\Enums\ModuleKind;
use App\Platform\Registry\ModuleRegistry;

// ─────────────────────────────────────────────────────────────────────────────
// T1 — register() + find() returns the module by id
// ─────────────────────────────────────────────────────────────────────────────

it('finds_registered_module_by_id', function (): void {
    $registry = new ModuleRegistry();

    $module = new DomainModule(
        id: 'commerce',
        kind: ModuleKind::Domain,
        label: 'Commerce',
    );

    $registry->register($module);

    $found = $registry->find('commerce') ?? throw new \DomainException('Module not found.');

    expect($found->id)->toBe('commerce');
    expect($found->label)->toBe('Commerce');
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — ofKind(Domain) returns only domain modules
// ─────────────────────────────────────────────────────────────────────────────

it('returns_only_domain_modules_by_kind', function (): void {
    $registry = new ModuleRegistry();

    $domain = new DomainModule(id: 'commerce', kind: ModuleKind::Domain, label: 'Commerce');
    $sector = new DomainModule(id: 'fashion', kind: ModuleKind::Sector, label: 'Fashion');

    $registry->register($domain);
    $registry->register($sector);

    $domains = $registry->ofKind(ModuleKind::Domain);

    expect($domains)->toHaveCount(1);
    expect($domains[0]->id)->toBe('commerce');
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — loadFromFile() registers a module from a PHP manifest
// ─────────────────────────────────────────────────────────────────────────────

it('loads_module_from_manifest_file', function (): void {
    $registry = new ModuleRegistry();

    $tempFile = tempnam(sys_get_temp_dir(), 'manifest_') . '.php';
    file_put_contents($tempFile, <<<'PHP'
<?php
use App\Platform\Registry\DomainModule;
use App\Platform\Registry\Enums\ModuleKind;
return new DomainModule(id: 'payroll', kind: ModuleKind::Domain, label: 'Payroll');
PHP);

    $registry->loadFromFile($tempFile);

    $payroll = $registry->find('payroll') ?? throw new \DomainException('Module not found.');

    expect($payroll->label)->toBe('Payroll');

    unlink($tempFile);
});

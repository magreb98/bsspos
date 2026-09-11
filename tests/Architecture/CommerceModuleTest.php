<?php

declare(strict_types=1);

/**
 * Architecture tests for the commerce module — Task 1.1
 *
 * Decisions:
 *   D1 — app-modules/commerce/src/ with Public/, Contracts/, Internal/
 *   D8 — Commerce manifest has migrations key
 */

// ─────────────────────────────────────────────────────────────────────────────
// T6 — Commerce module src/ subdirectories exist
// ─────────────────────────────────────────────────────────────────────────────

test('commerce_module_public_directory_exists', function (): void {
    expect(base_path('app-modules/commerce/src/Public'))->toBeDirectory();
});

test('commerce_module_contracts_directory_exists', function (): void {
    expect(base_path('app-modules/commerce/src/Contracts'))->toBeDirectory();
});

test('commerce_module_internal_directory_exists', function (): void {
    expect(base_path('app-modules/commerce/src/Internal'))->toBeDirectory();
});

// ─────────────────────────────────────────────────────────────────────────────
// T8 — Commerce manifest exposes a migrations path
// ─────────────────────────────────────────────────────────────────────────────

test('commerce_manifest_has_migrations_key', function (): void {
    /** @var \App\Platform\Registry\DomainModule $module */
    $module = require base_path('app-modules/commerce/manifest.php');

    expect($module->migrations)->not->toBeNull();
    expect($module->migrations)->not->toBeEmpty();
});

<?php

declare(strict_types=1);

/**
 * Feature tests for Registry service provider — Task 0.6: Registry, manifests, translation and socle:verifier
 *
 * Decisions:
 *   D3 — ModuleRegistry singleton loaded from app-modules/{module}/manifest.php at boot
 *   D4 — commerce manifest placeholder registered at boot
 *   D5 — lang/fr/errors.php and lang/en/errors.php both exist and are valid
 *   D7 — socle:verifier returns exit 0 on the current codebase
 */

use App\Platform\Registry\ModuleRegistry;

// ─────────────────────────────────────────────────────────────────────────────
// T8 — socle:verifier exits 0 on the current codebase
// ─────────────────────────────────────────────────────────────────────────────

it('socle_verifier_exits_zero_on_current_codebase', function (): void {
    /** @var \Tests\TestCase $this */
    $this->artisan('socle:verifier')->assertExitCode(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// T9 — ModuleRegistry contains the 'commerce' module after boot
// ─────────────────────────────────────────────────────────────────────────────

it('registry_contains_commerce_module_after_boot', function (): void {
    $registry = app(ModuleRegistry::class);

    $module = $registry->find('commerce');
    expect($module)->not->toBeNull();
    expect(($module ?? throw new \DomainException('No module.'))->id)->toBe('commerce');
});

// ─────────────────────────────────────────────────────────────────────────────
// T10 — lang/fr/errors.php and lang/en/errors.php exist and return arrays
// ─────────────────────────────────────────────────────────────────────────────

it('lang_error_files_exist_and_return_arrays', function (): void {
    $fr = base_path('lang/fr/errors.php');
    $en = base_path('lang/en/errors.php');

    expect(file_exists($fr))->toBeTrue("lang/fr/errors.php does not exist");
    expect(file_exists($en))->toBeTrue("lang/en/errors.php does not exist");

    expect(require $fr)->toBeArray();
    expect(require $en)->toBeArray();
});

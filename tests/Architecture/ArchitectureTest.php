<?php

declare(strict_types=1);

/**
 * Architecture tests — Task 0.1: Laravel 13 / PHP 8.4 skeleton and tooling
 *
 * These tests are RED before the Coder's work (the target directory structure and
 * configuration files do not exist yet).
 * They become GREEN once the Coder has completed task 0.1.
 *
 * Source of truth: socle-prompt/etat/conception-0.1.md
 */

// ─────────────────────────────────────────────────────────────────────────────
// NOMINAL — the target directory structure exists
// ─────────────────────────────────────────────────────────────────────────────

test('it_includes_the_platform_folder', function (): void {
    expect(base_path('app/Platform'))->toBeDirectory();
});

test('it_includes_the_tenancy_subfolder', function (): void {
    expect(base_path('app/Platform/Tenancy'))->toBeDirectory();
});

test('it_includes_the_identity_subfolder', function (): void {
    expect(base_path('app/Platform/Identity'))->toBeDirectory();
});

test('it_includes_the_audit_subfolder', function (): void {
    expect(base_path('app/Platform/Audit'))->toBeDirectory();
});

test('it_includes_the_money_subfolder', function (): void {
    expect(base_path('app/Platform/Money'))->toBeDirectory();
});

test('it_includes_the_sequencing_subfolder', function (): void {
    expect(base_path('app/Platform/Sequencing'))->toBeDirectory();
});

test('it_includes_the_idempotency_subfolder', function (): void {
    expect(base_path('app/Platform/Idempotency'))->toBeDirectory();
});

test('it_includes_the_events_subfolder', function (): void {
    expect(base_path('app/Platform/Events'))->toBeDirectory();
});

test('it_includes_the_registry_subfolder', function (): void {
    expect(base_path('app/Platform/Registry'))->toBeDirectory();
});

test('it_includes_the_documents_subfolder', function (): void {
    expect(base_path('app/Platform/Documents'))->toBeDirectory();
});

test('it_includes_the_http_subfolder', function (): void {
    expect(base_path('app/Platform/Http'))->toBeDirectory();
});

test('it_includes_the_control_folder', function (): void {
    expect(base_path('app/Control'))->toBeDirectory();
});

test('it_includes_the_app_modules_folder', function (): void {
    expect(base_path('app-modules'))->toBeDirectory();
});

// ─────────────────────────────────────────────────────────────────────────────
// CONFIG FILES — tools are configured
// ─────────────────────────────────────────────────────────────────────────────

test('deptrac_yaml_exists_at_root', function (): void {
    expect(base_path('deptrac.yaml'))->toBeFile();
});

test('phpstan_neon_exists_at_root', function (): void {
    expect(base_path('phpstan.neon'))->toBeFile();
});

test('phpstan_neon_configures_level_eight', function (): void {
    $content = (string) file_get_contents(base_path('phpstan.neon'));

    expect($content)->toContain('level: 8');
});

test('pint_json_exists_at_root', function (): void {
    expect(base_path('pint.json'))->toBeFile();
});

test('rector_php_exists_at_root', function (): void {
    expect(base_path('rector.php'))->toBeFile();
});

// ─────────────────────────────────────────────────────────────────────────────
// PHP FLOOR — the required version is correct
// ─────────────────────────────────────────────────────────────────────────────

test('composer_json_requires_php_8_4_minimum', function (): void {
    $composer = json_decode(
        (string) file_get_contents(base_path('composer.json')),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    $phpVersion = $composer['require']['php'] ?? '';

    expect($phpVersion)->toStartWith('^8.4');
});

// ─────────────────────────────────────────────────────────────────────────────
// GIT INTEGRITY — app-modules/ is not ignored
// ─────────────────────────────────────────────────────────────────────────────

test('gitignore_does_not_contain_app_modules_with_slash', function (): void {
    $content = (string) file_get_contents(base_path('.gitignore'));

    // Check line by line that no rule excludes app-modules/
    $lines = array_map(fn ($v) => trim((string) $v), explode("\n", $content));

    foreach ($lines as $line) {
        // Ignore comments and empty lines
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        expect($line)->not->toBe('app-modules/')
            ->and($line)->not->toBe('app-modules')
            ->and($line)->not->toBe('/app-modules/')
            ->and($line)->not->toBe('/app-modules');
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// DEPTRAC NAMING — the correct package is referenced
// ─────────────────────────────────────────────────────────────────────────────

test('composer_json_does_not_contain_the_abandoned_deptrac_package', function (): void {
    $composer = json_decode(
        (string) file_get_contents(base_path('composer.json')),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    $allDependencies = array_merge(
        array_keys($composer['require'] ?? []),
        array_keys($composer['require-dev'] ?? []),
    );

    expect($allDependencies)->not->toContain('qossmic/deptrac');
});

test('composer_json_contains_the_official_deptrac_package', function (): void {
    $composer = json_decode(
        (string) file_get_contents(base_path('composer.json')),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    $allDependencies = array_merge(
        array_keys($composer['require'] ?? []),
        array_keys($composer['require-dev'] ?? []),
    );

    expect($allDependencies)->toContain('deptrac/deptrac');
});

// ─────────────────────────────────────────────────────────────────────────────
// T11 — INVARIANT RULE 1 — Platform/Identity contains no business references
// Task 0.4: the Identity sub-module remains pure platform, without business terms
// ─────────────────────────────────────────────────────────────────────────────

test('it_contains_no_business_terms_under_platform_identity', function (): void {
    // Rule 1 — The platform knows no business domain (CLAUDE.md)
    $identityPath = base_path('app/Platform/Identity');

    // The folder must exist — otherwise the test is RED for the right reason
    expect($identityPath)->toBeDirectory();

    $businessTerms = ['produit', 'vente', 'salaire', 'vehicule', 'stock'];

    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($identityPath, \FilesystemIterator::SKIP_DOTS),
    );

    $violations = [];

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $content = strtolower((string) file_get_contents($file->getPathname()));

        foreach ($businessTerms as $term) {
            if (str_contains($content, $term)) {
                $violations[] = "{$file->getFilename()} contains '{$term}'";
            }
        }
    }

    expect($violations)->toBeEmpty(
        'Rule 1 violation in app/Platform/Identity/: ' . implode(', ', $violations)
    );
});

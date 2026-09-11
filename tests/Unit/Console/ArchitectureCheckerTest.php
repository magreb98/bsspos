<?php

declare(strict_types=1);

/**
 * Unit tests for ArchitectureChecker — Task 0.6: Registry, manifests, translation and socle:verifier
 *
 * Decisions:
 *   D6 — ArchitectureChecker injectable basePath; each check returns list<string> of violations
 *   C1 — No business terms under app/Platform/
 *   C4 — No float for amounts under app/
 *   C5 — All migrations have a non-empty down()
 */

use App\Console\ArchitectureChecker;

// ─────────────────────────────────────────────────────────────────────────────
// T4 — C1 detects business term 'produit' in a temp file under Platform/
// ─────────────────────────────────────────────────────────────────────────────

it('c1_detects_business_term_in_platform', function (): void {
    $base = sys_get_temp_dir() . '/checker_' . uniqid();
    mkdir($base . '/app/Platform', 0777, true);

    $file = $base . '/app/Platform/SomeService.php';
    file_put_contents($file, "<?php\n\n// lists all produit entries\nreturn [];\n");

    $checker    = new ArchitectureChecker($base);
    $violations = $checker->checkBusinessTermsInPlatform();

    expect($violations)->not->toBeEmpty();
    expect($violations[0])->toContain('produit');

    // cleanup
    unlink($file);
    rmdir($base . '/app/Platform');
    rmdir($base . '/app');
    rmdir($base);
});

// ─────────────────────────────────────────────────────────────────────────────
// T5 — C1 returns empty list on a clean Platform directory
// ─────────────────────────────────────────────────────────────────────────────

it('c1_returns_empty_on_clean_platform', function (): void {
    $base = sys_get_temp_dir() . '/checker_' . uniqid();
    mkdir($base . '/app/Platform', 0777, true);

    $file = $base . '/app/Platform/CleanService.php';
    file_put_contents($file, "<?php\n\nreturn [];\n");

    $checker    = new ArchitectureChecker($base);
    $violations = $checker->checkBusinessTermsInPlatform();

    expect($violations)->toBeEmpty();

    unlink($file);
    rmdir($base . '/app/Platform');
    rmdir($base . '/app');
    rmdir($base);
});

// ─────────────────────────────────────────────────────────────────────────────
// T6 — C4 detects ': float' type annotation in a temp file under app/
// ─────────────────────────────────────────────────────────────────────────────

it('c4_detects_float_type_annotation_under_app', function (): void {
    $base = sys_get_temp_dir() . '/checker_' . uniqid();
    mkdir($base . '/app', 0777, true);

    $file = $base . '/app/BadAmount.php';
    file_put_contents($file, "<?php\n\npublic function total(): float\n{\n    return 0.0;\n}\n");

    $checker    = new ArchitectureChecker($base);
    $violations = $checker->checkNoFloatForAmounts();

    expect($violations)->not->toBeEmpty();
    expect($violations[0])->toContain('float');

    unlink($file);
    rmdir($base . '/app');
    rmdir($base);
});

// ─────────────────────────────────────────────────────────────────────────────
// T7 — C5 detects a migration with an empty down()
// ─────────────────────────────────────────────────────────────────────────────

it('c5_detects_migration_without_down', function (): void {
    $base = sys_get_temp_dir() . '/checker_' . uniqid();
    mkdir($base . '/database/migrations', 0777, true);

    $file = $base . '/database/migrations/2026_01_01_000001_create_test_table.php';
    file_put_contents($file, <<<'PHP'
<?php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('test', fn ($t) => $t->id());
    }

    public function down(): void
    {
    }
};
PHP);

    $checker    = new ArchitectureChecker($base);
    $violations = $checker->checkMigrationsHaveDown();

    expect($violations)->not->toBeEmpty();

    unlink($file);
    rmdir($base . '/database/migrations');
    rmdir($base . '/database');
    rmdir($base);
});

// ─────────────────────────────────────────────────────────────────────────────
// T8 — C6 detects discount_amount declared as decimal instead of bigInteger
// ─────────────────────────────────────────────────────────────────────────────

it('c6_detects_decimal_discount_amount', function (): void {
    $base = sys_get_temp_dir() . '/checker_' . uniqid();
    mkdir($base . '/app-modules/commerce/database/migrations', 0777, true);

    $file = $base . '/app-modules/commerce/database/migrations/2026_bad_discount.php';
    file_put_contents($file, <<<'PHP'
<?php
Schema::table('sale_lines', function (Blueprint $table) {
    $table->decimal('discount_amount', 15, 4)->default(0);
});
PHP);

    $checker    = new ArchitectureChecker($base);
    $violations = $checker->checkDiscountAmountIsInteger();

    expect($violations)->not->toBeEmpty();
    expect($violations[0])->toContain('[C6]');

    unlink($file);
    rmdir($base . '/app-modules/commerce/database/migrations');
    rmdir($base . '/app-modules/commerce/database');
    rmdir($base . '/app-modules/commerce');
    rmdir($base . '/app-modules');
    rmdir($base);
});

// ─────────────────────────────────────────────────────────────────────────────
// T9 — C6 returns empty when discount_amount is bigInteger
// ─────────────────────────────────────────────────────────────────────────────

it('c6_returns_empty_on_biginteger_discount_amount', function (): void {
    $base = sys_get_temp_dir() . '/checker_' . uniqid();
    mkdir($base . '/app-modules', 0777, true);

    $file = $base . '/app-modules/clean.php';
    file_put_contents($file, <<<'PHP'
<?php
Schema::table('sale_lines', function (Blueprint $table) {
    $table->bigInteger('discount_amount')->default(0);
});
PHP);

    $checker    = new ArchitectureChecker($base);
    $violations = $checker->checkDiscountAmountIsInteger();

    expect($violations)->toBeEmpty();

    unlink($file);
    rmdir($base . '/app-modules');
    rmdir($base);
});

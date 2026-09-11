<?php

declare(strict_types=1);

/**
 * Feature/Unit tests for product spreadsheet import — Task 1.5
 *
 * Decisions:
 *   D2 — ProductImporter::import(csvPath): ImportReport
 *   D3 — ImportReport: imported, skipped, errors[]
 *   D5 — validation rules per row
 *   D7 — service granularity: no StockLevel created
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Import\ProductImporter;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\StockLevel;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function writeCsv(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'import_') . '.csv';
    file_put_contents($path, $content);

    return $path;
}

// ─────────────────────────────────────────────────────────────────────────────
// T1 — valid CSV of 3 rows imports 3 products with no errors
// ─────────────────────────────────────────────────────────────────────────────

it('imports_valid_csv_with_no_errors', function (): void {
    $tenant = Tenant::create(['name' => 'Import Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $csv = writeCsv(
        "reference,label,family_name,selling_price,granularity\n" .
        "REF-001,Produit A,Électronique,5000,quantity\n" .
        "REF-002,Produit B,Électronique,3000,quantity\n" .
        "REF-003,Installation,Services,2000,service\n"
    );

    $report = (new ProductImporter())->import($csv);

    expect($report->imported)->toBe(3);
    expect($report->hasErrors())->toBeFalse();
    expect(Product::count())->toBe(3);

    unlink($csv);
    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — row with empty reference produces a line error
// ─────────────────────────────────────────────────────────────────────────────

it('reports_error_for_empty_reference', function (): void {
    $tenant = Tenant::create(['name' => 'Error Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $csv = writeCsv(
        "reference,label,family_name,selling_price,granularity\n" .
        ",Produit sans ref,Divers,1000,quantity\n"
    );

    $report = (new ProductImporter())->import($csv);

    expect($report->imported)->toBe(0);
    expect($report->hasErrors())->toBeTrue();
    expect($report->errors[0]->line)->toBe(2);
    expect($report->errors[0]->reason)->toContain('Reference');

    unlink($csv);
    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — row with invalid granularity produces a line error
// ─────────────────────────────────────────────────────────────────────────────

it('reports_error_for_invalid_granularity', function (): void {
    $tenant = Tenant::create(['name' => 'Error Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $csv = writeCsv(
        "reference,label,family_name,selling_price,granularity\n" .
        "REF-BAD,Produit,Divers,1000,invalid_value\n"
    );

    $report = (new ProductImporter())->import($csv);

    expect($report->imported)->toBe(0);
    expect($report->hasErrors())->toBeTrue();
    expect($report->errors[0]->reason)->toContain('granularity');

    unlink($csv);
    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — row with duplicate reference in database produces a line error
// ─────────────────────────────────────────────────────────────────────────────

it('reports_error_for_duplicate_reference', function (): void {
    $tenant = Tenant::create(['name' => 'Dup Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $csv = writeCsv(
        "reference,label,family_name,selling_price,granularity\n" .
        "DUP-001,Produit 1,Divers,1000,quantity\n" .
        "DUP-001,Produit 2,Divers,2000,quantity\n"
    );

    $report = (new ProductImporter())->import($csv);

    expect($report->imported)->toBe(1);
    expect($report->hasErrors())->toBeTrue();
    expect($report->errors[0]->reason)->toContain('DUP-001');

    unlink($csv);
    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T5 — import of 800 rows succeeds
// ─────────────────────────────────────────────────────────────────────────────

it('imports_800_references', function (): void {
    $tenant = Tenant::create(['name' => 'Bulk Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $lines = ["reference,label,family_name,selling_price,granularity\n"];
    for ($i = 1; $i <= 800; $i++) {
        $lines[] = "BULK-{$i},Produit {$i},Catalogue,{$i}00,quantity\n";
    }
    $csv = writeCsv(implode('', $lines));

    $report = (new ProductImporter())->import($csv);

    expect($report->imported)->toBe(800);
    expect($report->hasErrors())->toBeFalse();
    expect(Product::count())->toBe(800);

    unlink($csv);
    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T6 — service product import creates no StockLevel
// ─────────────────────────────────────────────────────────────────────────────

it('service_product_import_creates_no_stock_level', function (): void {
    $tenant = Tenant::create(['name' => 'Service Import Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $csv = writeCsv(
        "reference,label,family_name,selling_price,granularity\n" .
        "SRV-IMPORT,Installation réseau,Services,5000,service\n"
    );

    (new ProductImporter())->import($csv);

    $product = Product::where('reference', 'SRV-IMPORT')->firstOrFail();

    expect($product->granularity)->toBe(Granularity::Service);
    expect(StockLevel::where('product_id', $product->id)->count())->toBe(0);

    unlink($csv);
    tenancy()->end();
});

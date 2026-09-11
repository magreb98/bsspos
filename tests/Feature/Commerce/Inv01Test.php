<?php

declare(strict_types=1);

/**
 * Feature test for INV-01 — Task 4.4
 *
 * INV-01: net stock = SUM(stock_movements.quantity) for a given product.
 * Validated with 10 000 random movements (deterministic seed).
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\StockMovement;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

it('inv01_sum_of_10000_random_movements_matches_db_sum', function (): void {
    $tenant = Tenant::create(['name' => 'INV01 Corp', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $unit    = OrganizationalUnit::create(['name' => 'INV01 HQ', 'active' => true]);
    $pos     = PointOfSale::create(['name' => 'INV01 POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $family  = Family::create(['name' => 'INV01 Family', 'active' => true]);
    $product = Product::create([
        'reference'     => 'INV01-X',
        'label'         => 'Test Product',
        'family_id'     => $family->id,
        'selling_price' => 1000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);

    mt_srand(42);

    $rows        = [];
    $expectedSum = 0;
    $now         = now()->toDateTimeString();

    for ($i = 0; $i < 10_000; $i++) {
        $qty = mt_rand(-50, 50);
        $expectedSum += $qty;
        $rows[]      = [
            'id'               => Uuid::uuid7()->toString(),
            'point_of_sale_id' => $pos->id,
            'product_id'       => $product->id,
            'sale_line_id'     => null,
            'quantity'         => $qty,
            'occurred_at'      => $now,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('stock_movements')->insert($chunk);
    }

    $dbSum = (int) StockMovement::where('product_id', $product->id)->sum('quantity');

    expect($dbSum)->toBe($expectedSum);

    tenancy()->end();
});

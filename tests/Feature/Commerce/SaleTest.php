<?php

declare(strict_types=1);

/**
 * Feature tests for cash sale and atomic confirmation — Task 2.2
 *
 * Decisions:
 *   D1  — sales table columns
 *   D2  — sale_lines table columns
 *   D3  — stock_movements table columns
 *   D4  — SaleState enum: Draft, Confirmed, Abandoned
 *   D5  — Sale model
 *   D6  — SaleConfirmationService: atomic transaction
 *   D7  — INV-04: total = sum of lines
 *   D8  — INV-07: sequential number via AllocateNumber
 *   D9  — INV-09: closed session refuses confirmation
 *   D10 — Confirmed sale is immutable
 *   D11 — Service granularity: no StockMovement
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\Commerce\Internal\Models\StockMovement;
use Modules\Commerce\Internal\Services\SaleConfirmationService;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeOpenSession(): CashSession
{
    $unit     = OrganizationalUnit::create(['name' => 'HQ', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'Main POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'R1', 'point_of_sale_id' => $pos->id, 'active' => true]);

    return CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);
}

function makeProduct(string $reference = 'REF-001', Granularity $granularity = Granularity::Quantity, int $price = 10000): Product
{
    $family = Family::firstOrCreate(['name' => 'Test Family', 'active' => true]);

    return Product::create([
        'reference'     => $reference,
        'label'         => "Product {$reference}",
        'family_id'     => $family->id,
        'selling_price' => $price,
        'vat_rate'      => '19.25',
        'granularity'   => $granularity,
        'active'        => true,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// T1 — sales and sale_lines tables have required columns
// ─────────────────────────────────────────────────────────────────────────────

it('sales_and_sale_lines_tables_have_required_columns', function (): void {
    $tenant = Tenant::create(['name' => 'Sale Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $saleColumns     = \Illuminate\Support\Facades\Schema::getColumnListing('sales');
    $saleLineColumns = \Illuminate\Support\Facades\Schema::getColumnListing('sale_lines');
    $movementColumns = \Illuminate\Support\Facades\Schema::getColumnListing('stock_movements');

    expect($saleColumns)->toContain(
        'id',
        'cash_session_id',
        'state',
        'number',
        'total_excluding_tax',
        'total_tax',
        'total_including_tax',
        'idempotency_key'
    );

    expect($saleLineColumns)->toContain(
        'id',
        'sale_id',
        'product_id',
        'designation',
        'unit_price',
        'vat_rate',
        'quantity',
        'line_total_excluding_tax',
        'line_total_tax',
        'line_total_including_tax'
    );

    expect($movementColumns)->toContain(
        'id',
        'point_of_sale_id',
        'product_id',
        'sale_line_id',
        'quantity',
        'occurred_at'
    );

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — create a draft sale with two lines
// ─────────────────────────────────────────────────────────────────────────────

it('can_create_draft_sale_with_lines', function (): void {
    $tenant = Tenant::create(['name' => 'Sale Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session  = makeOpenSession();
    $product1 = makeProduct('P-001');
    $product2 = makeProduct('P-002', Granularity::Quantity, 5000);
    $key      = Uuid::uuid7()->toString();

    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => $key,
    ]);

    SaleLine::create([
        'sale_id'    => $sale->id,
        'product_id' => $product1->id,
        'designation' => $product1->label,
        'unit_price' => 10000,
        'vat_rate'   => '19.25',
        'quantity'   => 2,
        'line_total_excluding_tax'  => 16763,
        'line_total_tax'            => 3237,
        'line_total_including_tax'  => 20000,
    ]);

    SaleLine::create([
        'sale_id'    => $sale->id,
        'product_id' => $product2->id,
        'designation' => $product2->label,
        'unit_price' => 5000,
        'vat_rate'   => '19.25',
        'quantity'   => 1,
        'line_total_excluding_tax'  => 4191,
        'line_total_tax'            => 809,
        'line_total_including_tax'  => 5000,
    ]);

    expect($sale->state)->toBe(SaleState::Draft);
    expect($sale->lines()->count())->toBe(2);
    expect($sale->number)->toBeNull();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — confirming a sale: state=confirmed, number assigned, totals coherent
// ─────────────────────────────────────────────────────────────────────────────

it('confirming_sale_sets_state_number_and_totals', function (): void {
    $tenant = Tenant::create(['name' => 'Sale Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeOpenSession();
    $product = makeProduct('P-C01', Granularity::Quantity, 10000);
    $key     = Uuid::uuid7()->toString();

    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => $key,
    ]);

    SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => $product->label,
        'unit_price'               => 10000,
        'vat_rate'                 => '19.25',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 8383,
        'line_total_tax'           => 1617,
        'line_total_including_tax' => 10000,
    ]);

    (new SaleConfirmationService())->confirm($sale);

    $sale->refresh();

    expect($sale->state)->toBe(SaleState::Confirmed);
    expect($sale->number)->not->toBeNull();
    expect($sale->confirmed_at)->not->toBeNull();
    expect($sale->total_including_tax?->toInt())->toBe(10000);
    expect($sale->total_excluding_tax?->toInt())->toBe(8383);
    expect($sale->total_tax?->toInt())->toBe(1617);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — INV-04: total_including_tax = sum of line_total_including_tax
// ─────────────────────────────────────────────────────────────────────────────

it('inv04_total_equals_sum_of_lines', function (): void {
    $tenant = Tenant::create(['name' => 'Sale Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeOpenSession();
    $p1      = makeProduct('D-001', Granularity::Quantity, 12000);
    $p2      = makeProduct('D-002', Granularity::Quantity, 8000);
    $p3      = makeProduct('D-003', Granularity::Quantity, 5000);
    $key     = Uuid::uuid7()->toString();

    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => $key,
    ]);

    foreach ([
        [$p1, 12000, 10063, 1937],
        [$p2, 8000, 6708, 1292],
        [$p3, 5000, 4191, 809],
    ] as [$product, $ttc, $ht, $tax]) {
        SaleLine::create([
            'sale_id'                  => $sale->id,
            'product_id'               => $product->id,
            'designation'              => $product->label,
            'unit_price'               => $ttc,
            'vat_rate'                 => '19.25',
            'quantity'                 => 1,
            'line_total_excluding_tax' => $ht,
            'line_total_tax'           => $tax,
            'line_total_including_tax' => $ttc,
        ]);
    }

    (new SaleConfirmationService())->confirm($sale);

    $sale->refresh();

    $expectedTtc = 12000 + 8000 + 5000;
    expect($sale->total_including_tax?->toInt())->toBe($expectedTtc);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T5 — INV-09: confirming in a closed session throws DomainException
// ─────────────────────────────────────────────────────────────────────────────

it('inv09_closed_session_refuses_confirmation', function (): void {
    $tenant = Tenant::create(['name' => 'Sale Corp E', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $unit     = OrganizationalUnit::create(['name' => 'HQ', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'R1', 'point_of_sale_id' => $pos->id, 'active' => true]);
    $userId   = Uuid::uuid7()->toString();

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Closed,
        'opened_at'        => now()->subHour(),
        'closed_at'        => now(),
        'opening_balance'  => 0,
        'closing_balance'  => 0,
        'opened_by'        => $userId,
        'closed_by'        => $userId,
    ]);

    $product = makeProduct('E-001');

    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);

    SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => $product->label,
        'unit_price'               => 5000,
        'vat_rate'                 => '19.25',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 4191,
        'line_total_tax'           => 809,
        'line_total_including_tax' => 5000,
    ]);

    expect(fn () => (new SaleConfirmationService())->confirm($sale))
        ->toThrow(\DomainException::class);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T6 — confirming an already-confirmed sale throws DomainException
// ─────────────────────────────────────────────────────────────────────────────

it('confirmed_sale_cannot_be_re_confirmed', function (): void {
    $tenant = Tenant::create(['name' => 'Sale Corp F', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeOpenSession();
    $product = makeProduct('F-001');

    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);

    SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => $product->label,
        'unit_price'               => 5000,
        'vat_rate'                 => '19.25',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 4191,
        'line_total_tax'           => 809,
        'line_total_including_tax' => 5000,
    ]);

    $service = new SaleConfirmationService();
    $service->confirm($sale);

    $sale->refresh();
    expect(fn () => $service->confirm($sale))
        ->toThrow(\DomainException::class);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T7 — service line: no StockMovement created
// ─────────────────────────────────────────────────────────────────────────────

it('service_line_creates_no_stock_movement', function (): void {
    $tenant = Tenant::create(['name' => 'Sale Corp G', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeOpenSession();
    $product = makeProduct('G-001', Granularity::Service, 5000);

    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);

    SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => $product->label,
        'unit_price'               => 5000,
        'vat_rate'                 => '19.25',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 4191,
        'line_total_tax'           => 809,
        'line_total_including_tax' => 5000,
    ]);

    (new SaleConfirmationService())->confirm($sale);

    expect(StockMovement::count())->toBe(0);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T8 — quantity line: one negative StockMovement created
// ─────────────────────────────────────────────────────────────────────────────

it('quantity_line_creates_negative_stock_movement', function (): void {
    $tenant = Tenant::create(['name' => 'Sale Corp H', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeOpenSession();
    $product = makeProduct('H-001', Granularity::Quantity, 10000);

    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);

    SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => $product->label,
        'unit_price'               => 10000,
        'vat_rate'                 => '19.25',
        'quantity'                 => 3,
        'line_total_excluding_tax' => 25149,
        'line_total_tax'           => 4851,
        'line_total_including_tax' => 30000,
    ]);

    (new SaleConfirmationService())->confirm($sale);

    expect(StockMovement::count())->toBe(1);
    $movement = StockMovement::firstOrFail();
    expect($movement->quantity)->toBe(-3);
    expect($movement->product_id)->toBe($product->id);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T9 — commerce.sale.confirmed event written to outbox
// ─────────────────────────────────────────────────────────────────────────────

it('confirmation_writes_event_to_outbox', function (): void {
    $tenant = Tenant::create(['name' => 'Sale Corp I', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $session = makeOpenSession();
    $product = makeProduct('I-001', Granularity::Quantity, 10000);

    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);

    SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => $product->label,
        'unit_price'               => 10000,
        'vat_rate'                 => '19.25',
        'quantity'                 => 1,
        'line_total_excluding_tax' => 8383,
        'line_total_tax'           => 1617,
        'line_total_including_tax' => 10000,
    ]);

    (new SaleConfirmationService())->confirm($sale);

    $message = \Illuminate\Support\Facades\DB::table('outbox_messages')
        ->where('type', 'commerce.sale.confirmed')
        ->firstOrFail();

    $payload = json_decode($message->payload, true);
    expect($payload['sale_id'])->toBe($sale->id);

    tenancy()->end();
});

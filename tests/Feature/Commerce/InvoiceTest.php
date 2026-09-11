<?php

declare(strict_types=1);

/**
 * Feature tests for invoice building — Task 2.4
 *
 * Decisions:
 *   D1 — invoice_settings table: niu, rccm, company_name
 *   D2 — InvoiceData value object
 *   D3 — InvoiceLineData value object
 *   D4 — InvoiceBuilder::build(Sale): InvoiceData
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Documents\InvoiceData;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\InvoiceSetting;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\Commerce\Internal\Services\InvoiceBuilder;
use Modules\Commerce\Internal\Services\SaleConfirmationService;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function buildConfirmedSaleForInvoice(): Sale
{
    $unit     = OrganizationalUnit::create(['name' => 'INV HQ', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'INV POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'INV R1', 'point_of_sale_id' => $pos->id, 'active' => true]);

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);

    $family  = Family::firstOrCreate(['name' => 'INV Family', 'active' => true]);
    $product = Product::create([
        'reference'     => 'INV-P001',
        'label'         => 'Widget Pro',
        'family_id'     => $family->id,
        'selling_price' => 20000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Quantity,
        'active'        => true,
    ]);

    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);

    SaleLine::create([
        'sale_id'                  => $sale->id,
        'product_id'               => $product->id,
        'designation'              => $product->label,
        'unit_price'               => 20000,
        'vat_rate'                 => '19.25',
        'quantity'                 => 2,
        'line_total_excluding_tax' => 33557,
        'line_total_tax'           => 6443,
        'line_total_including_tax' => 40000,
    ]);

    (new SaleConfirmationService())->confirm($sale);

    $sale->refresh();

    return $sale;
}

// ─────────────────────────────────────────────────────────────────────────────
// T1 — invoice_settings table has required columns
// ─────────────────────────────────────────────────────────────────────────────

it('invoice_settings_table_has_required_columns', function (): void {
    $tenant = Tenant::create(['name' => 'Invoice Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('invoice_settings');

    expect($columns)->toContain('id', 'niu', 'rccm', 'company_name', 'address', 'phone');

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — InvoiceBuilder::build() returns a complete InvoiceData
// ─────────────────────────────────────────────────────────────────────────────

it('invoice_builder_returns_invoice_data', function (): void {
    $tenant = Tenant::create(['name' => 'Invoice Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    InvoiceSetting::create([
        'niu'          => 'M123456789A',
        'rccm'         => 'RC/DLA/2020/B/1234',
        'company_name' => 'Boutique Test SARL',
        'address'      => 'Akwa, Douala',
        'phone'        => '+237600000000',
    ]);

    $sale   = buildConfirmedSaleForInvoice();
    $result = (new InvoiceBuilder())->build($sale);

    expect($result)->toBeInstanceOf(InvoiceData::class);
    expect($result->invoiceNumber)->toBe($sale->number);
    expect($result->lines)->toHaveCount(1);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — InvoiceData contains NIU, RCCM, and the sale's number
// ─────────────────────────────────────────────────────────────────────────────

it('invoice_data_contains_niu_rccm_and_number', function (): void {
    $tenant = Tenant::create(['name' => 'Invoice Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    InvoiceSetting::create([
        'niu'          => 'P987654321B',
        'rccm'         => 'RC/YDE/2021/A/5678',
        'company_name' => 'Commerce Elite',
    ]);

    $sale   = buildConfirmedSaleForInvoice();
    $result = (new InvoiceBuilder())->build($sale);

    expect($result->niu)->toBe('P987654321B');
    expect($result->rccm)->toBe('RC/YDE/2021/A/5678');
    expect($result->invoiceNumber)->not->toBeEmpty();
    expect($result->vatRate)->toBe('19.25');

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — InvoiceData totals match the sale's confirmed totals
// ─────────────────────────────────────────────────────────────────────────────

it('invoice_data_totals_match_confirmed_sale', function (): void {
    $tenant = Tenant::create(['name' => 'Invoice Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    InvoiceSetting::create([
        'niu'          => 'X000000001C',
        'rccm'         => 'RC/DLA/2022/B/9999',
        'company_name' => 'Test Commerce',
    ]);

    $sale   = buildConfirmedSaleForInvoice();
    $result = (new InvoiceBuilder())->build($sale);

    expect($result->totalIncludingTax)->toBe($sale->total_including_tax?->toInt() ?? 0);
    expect($result->totalExcludingTax)->toBe($sale->total_excluding_tax?->toInt() ?? 0);
    expect($result->totalTax)->toBe($sale->total_tax?->toInt() ?? 0);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T5 — building an invoice for a draft sale throws DomainException
// ─────────────────────────────────────────────────────────────────────────────

it('building_invoice_for_draft_sale_throws', function (): void {
    $tenant = Tenant::create(['name' => 'Invoice Corp E', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    InvoiceSetting::create([
        'niu'          => 'Z999999999D',
        'rccm'         => 'RC/DLA/2023/A/0001',
        'company_name' => 'Draft Corp',
    ]);

    $unit     = OrganizationalUnit::create(['name' => 'Draft HQ', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'Draft POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'Draft R1', 'point_of_sale_id' => $pos->id, 'active' => true]);

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);

    $draftSale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);

    expect(fn () => (new InvoiceBuilder())->build($draftSale))
        ->toThrow(\DomainException::class);

    tenancy()->end();
});

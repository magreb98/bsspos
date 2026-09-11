<?php

declare(strict_types=1);

/**
 * Feature tests for payment collection — Task 2.3
 *
 * Decisions:
 *   D1 — PaymentMethod enum: Cash, MobileMoney
 *   D2 — PaymentStatus enum: Pending, Confirmed, Failed
 *   D3 — payments table
 *   D4 — PaymentCollectionService: collect()
 *   D5 — INV-05: sum of confirmed payments <= total_including_tax
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\PaymentMethod;
use Modules\Commerce\Internal\Enums\PaymentStatus;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\Payment;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\Commerce\Internal\Services\PaymentCollectionService;
use Modules\Commerce\Internal\Services\SaleConfirmationService;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function buildConfirmedSale(int $ttc = 10000): Sale
{
    $unit     = OrganizationalUnit::create(['name' => 'HQ-Pay', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'Pay POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'Pay R1', 'point_of_sale_id' => $pos->id, 'active' => true]);

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);

    $family  = Family::firstOrCreate(['name' => 'Pay Family', 'active' => true]);
    $product = Product::create([
        'reference'     => 'PAY-' . uniqid(),
        'label'         => 'Pay Product',
        'family_id'     => $family->id,
        'selling_price' => $ttc,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Service,
        'active'        => true,
    ]);

    $ht  = (int) round($ttc / 1.1925);
    $tax = $ttc - $ht;

    $sale = Sale::create([
        'cash_session_id' => $session->id,
        'state'           => SaleState::Draft,
        'idempotency_key' => Uuid::uuid7()->toString(),
    ]);

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

    (new SaleConfirmationService())->confirm($sale);

    $sale->refresh();

    return $sale;
}

// ─────────────────────────────────────────────────────────────────────────────
// T1 — payments table has required columns
// ─────────────────────────────────────────────────────────────────────────────

it('payments_table_has_required_columns', function (): void {
    $tenant = Tenant::create(['name' => 'Pay Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('payments');

    expect($columns)->toContain('id', 'sale_id', 'method', 'amount', 'status', 'reference', 'confirmed_at');

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — cash payment: status immediately confirmed
// ─────────────────────────────────────────────────────────────────────────────

it('cash_payment_is_immediately_confirmed', function (): void {
    $tenant = Tenant::create(['name' => 'Pay Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $sale    = buildConfirmedSale(10000);
    $service = new PaymentCollectionService();

    $payment = $service->collect($sale, PaymentMethod::Cash, 10000);

    expect($payment->method)->toBe(PaymentMethod::Cash);
    expect($payment->status)->toBe(PaymentStatus::Confirmed);
    expect($payment->amount?->toInt())->toBe(10000);
    expect($payment->confirmed_at)->not->toBeNull();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — mobile money payment: status pending
// ─────────────────────────────────────────────────────────────────────────────

it('mobile_money_payment_starts_as_pending', function (): void {
    $tenant = Tenant::create(['name' => 'Pay Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $sale    = buildConfirmedSale(10000);
    $service = new PaymentCollectionService();

    $payment = $service->collect($sale, PaymentMethod::MobileMoney, 10000, 'OM-REF-001');

    expect($payment->method)->toBe(PaymentMethod::MobileMoney);
    expect($payment->status)->toBe(PaymentStatus::Pending);
    expect($payment->reference)->toBe('OM-REF-001');
    expect($payment->confirmed_at)->toBeNull();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — mixed payment: two payments reaching the total
// ─────────────────────────────────────────────────────────────────────────────

it('mixed_payment_accepts_two_installments', function (): void {
    $tenant = Tenant::create(['name' => 'Pay Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $sale    = buildConfirmedSale(15000);
    $service = new PaymentCollectionService();

    $p1 = $service->collect($sale, PaymentMethod::Cash, 5000);
    $p2 = $service->collect($sale, PaymentMethod::MobileMoney, 10000, 'OM-REF-002');

    expect(Payment::where('sale_id', $sale->id)->count())->toBe(2);
    expect($p1->status)->toBe(PaymentStatus::Confirmed);
    expect($p2->status)->toBe(PaymentStatus::Pending);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T5 — INV-05: exceeding total raises DomainException
// ─────────────────────────────────────────────────────────────────────────────

it('inv05_exceeding_total_throws', function (): void {
    $tenant = Tenant::create(['name' => 'Pay Corp E', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $sale    = buildConfirmedSale(10000);
    $service = new PaymentCollectionService();

    $service->collect($sale, PaymentMethod::Cash, 8000);

    expect(fn () => $service->collect($sale, PaymentMethod::Cash, 5000))
        ->toThrow(\DomainException::class);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T6 — isFullyPaid() returns true when confirmed payments reach the total
// ─────────────────────────────────────────────────────────────────────────────

it('is_fully_paid_returns_true_when_total_reached', function (): void {
    $tenant = Tenant::create(['name' => 'Pay Corp F', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $sale    = buildConfirmedSale(10000);
    $service = new PaymentCollectionService();

    expect($service->isFullyPaid($sale))->toBeFalse();

    $service->collect($sale, PaymentMethod::Cash, 10000);

    expect($service->isFullyPaid($sale))->toBeTrue();

    tenancy()->end();
});

<?php

declare(strict_types=1);

/**
 * Tests for mobile money callback handling — Task 2.5
 *
 * Decisions:
 *   D1 — HMAC signature verification
 *   D2 — MobileMoneyCallbackHandler: handle()
 *   D3 — MobileMoneyPoller: poll()
 *   D4 — MobileMoneyGatewayClient interface (stub in tests)
 *   D5 — Idempotence: duplicate reference ignored
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Enums\PaymentMethod;
use Modules\Commerce\Internal\Enums\PaymentStatus;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Gateways\MobileMoneyGatewayClient;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\Payment;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\Commerce\Internal\Services\MobileMoneyCallbackHandler;
use Modules\Commerce\Internal\Services\MobileMoneyPoller;
use Modules\Commerce\Internal\Services\PaymentCollectionService;
use Modules\Commerce\Internal\Services\SaleConfirmationService;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

const MOBILE_MONEY_SECRET = 'test-secret-key';

function makeConfirmedSaleWithPendingPayment(string $reference, int $ttc = 10000): Payment
{
    $unit     = OrganizationalUnit::create(['name' => 'MM HQ', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'MM POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'MM R1', 'point_of_sale_id' => $pos->id, 'active' => true]);

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => Uuid::uuid7()->toString(),
    ]);

    $family  = Family::firstOrCreate(['name' => 'MM Family', 'active' => true]);
    $product = Product::create([
        'reference'     => 'MM-' . uniqid(),
        'label'         => 'MM Product',
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

    return (new PaymentCollectionService())->collect($sale, PaymentMethod::MobileMoney, $ttc, $reference);
}

function makeSignature(string $body, string $secret = MOBILE_MONEY_SECRET): string
{
    return 'sha256=' . hash_hmac('sha256', $body, $secret);
}

// ─────────────────────────────────────────────────────────────────────────────
// T1 — valid HMAC signature passes
// ─────────────────────────────────────────────────────────────────────────────

it('valid_signature_allows_processing', function (): void {
    $tenant = Tenant::create(['name' => 'MM Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $reference = 'OM-VALID-001';
    makeConfirmedSaleWithPendingPayment($reference);

    $body      = (string) json_encode(['reference' => $reference, 'status' => 'success']);
    $signature = makeSignature($body);

    $handler = new MobileMoneyCallbackHandler(MOBILE_MONEY_SECRET);
    $handler->handle($reference, 'success', $body, $signature);

    expect(Payment::where('reference', $reference)->firstOrFail()->status)->toBe(PaymentStatus::Confirmed);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — invalid HMAC signature throws InvalidArgumentException
// ─────────────────────────────────────────────────────────────────────────────

it('invalid_signature_throws', function (): void {
    $tenant = Tenant::create(['name' => 'MM Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $reference = 'OM-INVALID-001';
    makeConfirmedSaleWithPendingPayment($reference);

    $body = (string) json_encode(['reference' => $reference, 'status' => 'success']);

    $handler = new MobileMoneyCallbackHandler(MOBILE_MONEY_SECRET);

    expect(fn () => $handler->handle($reference, 'success', $body, 'sha256=wronghash'))
        ->toThrow(\InvalidArgumentException::class);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — success callback confirms the payment
// ─────────────────────────────────────────────────────────────────────────────

it('success_callback_confirms_payment', function (): void {
    $tenant = Tenant::create(['name' => 'MM Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $reference = 'OM-SUCCESS-001';
    makeConfirmedSaleWithPendingPayment($reference);

    $body      = (string) json_encode(['reference' => $reference, 'status' => 'success']);
    $signature = makeSignature($body);

    (new MobileMoneyCallbackHandler(MOBILE_MONEY_SECRET))->handle($reference, 'success', $body, $signature);

    $payment = Payment::where('reference', $reference)->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Confirmed);
    expect($payment->confirmed_at)->not->toBeNull();

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — duplicate callback is idempotent
// ─────────────────────────────────────────────────────────────────────────────

it('duplicate_callback_is_idempotent', function (): void {
    $tenant = Tenant::create(['name' => 'MM Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $reference = 'OM-DUP-001';
    makeConfirmedSaleWithPendingPayment($reference);

    $body      = (string) json_encode(['reference' => $reference, 'status' => 'success']);
    $signature = makeSignature($body);
    $handler   = new MobileMoneyCallbackHandler(MOBILE_MONEY_SECRET);

    $handler->handle($reference, 'success', $body, $signature);
    $handler->handle($reference, 'success', $body, $signature);

    expect(Payment::where('reference', $reference)->count())->toBe(1);
    expect(Payment::where('reference', $reference)->firstOrFail()->status)->toBe(PaymentStatus::Confirmed);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T5 — poller processes stale pending payments via stub
// ─────────────────────────────────────────────────────────────────────────────

it('poller_processes_stale_pending_payments', function (): void {
    $tenant = Tenant::create(['name' => 'MM Corp E', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $reference = 'OM-STALE-001';
    $payment   = makeConfirmedSaleWithPendingPayment($reference);

    // Backdate created_at to simulate a stale pending payment (> 2 min old)
    Payment::where('id', $payment->id)->update(['created_at' => now()->subMinutes(5)]);

    $stub = new class () implements MobileMoneyGatewayClient {
        public function queryStatus(string $reference): string
        {
            return 'success';
        }
    };

    $processed = (new MobileMoneyPoller($stub))->poll();

    expect($processed)->toBe(1);
    expect(Payment::where('reference', $reference)->firstOrFail()->status)->toBe(PaymentStatus::Confirmed);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T6 — poller ignores recent pending payments (< 2 min)
// ─────────────────────────────────────────────────────────────────────────────

it('poller_ignores_recent_pending_payments', function (): void {
    $tenant = Tenant::create(['name' => 'MM Corp F', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $reference = 'OM-RECENT-001';
    makeConfirmedSaleWithPendingPayment($reference);

    $stub = new class () implements MobileMoneyGatewayClient {
        public function queryStatus(string $reference): string
        {
            return 'success';
        }
    };

    $processed = (new MobileMoneyPoller($stub))->poll();

    expect($processed)->toBe(0);
    expect(Payment::where('reference', $reference)->firstOrFail()->status)->toBe(PaymentStatus::Pending);

    tenancy()->end();
});

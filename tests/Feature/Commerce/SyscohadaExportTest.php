<?php

declare(strict_types=1);

/**
 * Feature tests for SYSCOHADA export — Task 6.4
 *
 * Decisions:
 *   D1 — JournalEntry: append-only, emitted_at set at creation
 *   D2 — SyscohadaExportService::emitForSale(Sale): 3 balanced lines per confirmed sale
 *   D3 — Idempotency: replay returns existing entries, no duplicate inserts
 *   D4 — DomainException if sale not confirmed
 */

use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\JournalEntry;
use Modules\Commerce\Internal\Models\OrganizationalUnit;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Services\SyscohadaExportService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeSyscohadaSale(string $number, int $ht, int $tax, int $ttc, SaleState $state = SaleState::Confirmed): Sale
{
    $unit     = OrganizationalUnit::create(['name' => 'Syscohada Unit', 'active' => true]);
    $pos      = PointOfSale::create(['name' => 'Syscohada POS', 'organizational_unit_id' => $unit->id, 'active' => true]);
    $register = CashRegister::create(['name' => 'Caisse S', 'point_of_sale_id' => $pos->id, 'active' => true]);
    $user     = User::create(['first_name' => 'S', 'last_name' => 'User', 'phone' => '+237600990001', 'password' => bcrypt('x')]);

    $session = CashSession::create([
        'cash_register_id' => $register->id,
        'state'            => SessionState::Open,
        'opened_at'        => now(),
        'opening_balance'  => 0,
        'opened_by'        => $user->id,
    ]);

    return Sale::create([
        'cash_session_id'      => $session->id,
        'state'                => $state,
        'number'               => $number,
        'total_excluding_tax'  => $ht,
        'total_tax'            => $tax,
        'total_including_tax'  => $ttc,
        'idempotency_key'      => 'idem-' . $number,
        'confirmed_at'         => $state === SaleState::Confirmed ? now() : null,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// E1 — emitForSale creates 3 balanced journal lines for a confirmed sale
// ─────────────────────────────────────────────────────────────────────────────

it('emit_for_sale_creates_three_balanced_journal_lines', function (): void {
    $tenant = Tenant::create(['name' => 'Syscohada Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $sale = makeSyscohadaSale('V-2026-0001', 83765, 16124, 99889);

    $svc     = new SyscohadaExportService();
    $entries = $svc->emitForSale($sale);

    expect(count($entries))->toBe(3);

    $accounts = array_map(fn (JournalEntry $e) => $e->account_number, $entries);
    expect($accounts)->toContain('571');
    expect($accounts)->toContain('701');
    expect($accounts)->toContain('443');

    $totalDebit  = array_sum(array_map(fn (JournalEntry $e) => $e->debit, $entries));
    $totalCredit = array_sum(array_map(fn (JournalEntry $e) => $e->credit, $entries));
    expect($totalDebit)->toBe($totalCredit);
    expect($totalDebit)->toBe(99889);

    expect(JournalEntry::count())->toBe(3);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// E2 — Re-calling emitForSale is idempotent — no duplicate rows in DB
// ─────────────────────────────────────────────────────────────────────────────

it('emit_for_sale_is_idempotent_on_replay', function (): void {
    $tenant = Tenant::create(['name' => 'Syscohada Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $sale = makeSyscohadaSale('V-2026-0002', 41883, 8067, 49950);

    $svc = new SyscohadaExportService();
    $svc->emitForSale($sale);
    $svc->emitForSale($sale);
    $svc->emitForSale($sale);

    expect(JournalEntry::count())->toBe(3);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// E3 — Emitted entries have emitted_at set and are returned unchanged on replay
// ─────────────────────────────────────────────────────────────────────────────

it('emitted_entry_has_emitted_at_and_is_unchanged_on_replay', function (): void {
    $tenant = Tenant::create(['name' => 'Syscohada Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $sale = makeSyscohadaSale('V-2026-0003', 25130, 4838, 29968);

    $svc          = new SyscohadaExportService();
    $first        = $svc->emitForSale($sale);
    $firstEmitted = array_map(fn (JournalEntry $e) => $e->emitted_at->toIso8601String(), $first);

    $second        = $svc->emitForSale($sale);
    $secondEmitted = array_map(fn (JournalEntry $e) => $e->emitted_at->toIso8601String(), $second);

    foreach ($first as $entry) {
        expect($entry->emitted_at)->not->toBeNull();
    }

    expect($firstEmitted)->toBe($secondEmitted);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// E4 — emitForSale on a non-confirmed sale throws DomainException
// ─────────────────────────────────────────────────────────────────────────────

it('emit_for_sale_throws_for_non_confirmed_sale', function (): void {
    $tenant = Tenant::create(['name' => 'Syscohada Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $sale = makeSyscohadaSale('V-2026-0004', 41883, 8067, 49950, SaleState::Draft);

    $svc = new SyscohadaExportService();

    expect(fn () => $svc->emitForSale($sale))->toThrow(\DomainException::class);

    tenancy()->end();
});

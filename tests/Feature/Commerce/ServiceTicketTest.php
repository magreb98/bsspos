<?php

declare(strict_types=1);

/**
 * Feature tests for SAV service tickets — Task 7.3
 *
 * Decisions:
 *   D1 — ServiceTicket: open/in_repair/closed lifecycle
 *   D2 — Opening a ticket transitions serial unit to InService
 *   D3 — Closing a ticket transitions unit back to Available
 *   D4 — DomainException on double-close
 */

use App\Control\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Enums\Granularity;
use Modules\Commerce\Internal\Models\Family;
use Modules\Commerce\Internal\Models\Product;
use Modules\SecteurElectronique\Enums\SerialStatus;
use Modules\SecteurElectronique\Enums\TicketStatus;
use Modules\SecteurElectronique\Models\SerialUnit;
use Modules\SecteurElectronique\Models\ServiceTicket;
use Modules\SecteurElectronique\Services\ServiceTicketService;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeSavUnit(string $serial): SerialUnit
{
    $family  = Family::create(['name' => 'SAV Family', 'active' => true]);
    $product = Product::create([
        'reference'     => 'SAV-001',
        'label'         => 'Device',
        'family_id'     => $family->id,
        'selling_price' => 100000,
        'vat_rate'      => '19.25',
        'granularity'   => Granularity::Serial,
        'active'        => true,
    ]);

    return SerialUnit::create([
        'product_id'    => $product->id,
        'serial_number' => $serial,
        'status'        => SerialStatus::Available,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// R1 — Opening a ticket sets unit to in_service and creates an open ticket
// ─────────────────────────────────────────────────────────────────────────────

it('open_sets_unit_in_service_and_creates_open_ticket', function (): void {
    $tenant = Tenant::create(['name' => 'SAV Corp A', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $unit   = makeSavUnit('SAV-SN-001');
    $svc    = new ServiceTicketService();
    $ticket = $svc->open($unit, 'Screen cracked');

    expect($ticket->status)->toBe(TicketStatus::Open);
    expect($ticket->description)->toBe('Screen cracked');
    expect($ticket->opened_at)->not->toBeNull();
    expect($ticket->closed_at)->toBeNull();

    $unit->refresh();
    expect($unit->status)->toBe(SerialStatus::InService);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// R2 — Closing a ticket sets closed_at, status = closed, restores unit to available
// ─────────────────────────────────────────────────────────────────────────────

it('close_sets_closed_at_and_restores_unit_to_available', function (): void {
    $tenant = Tenant::create(['name' => 'SAV Corp B', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $unit   = makeSavUnit('SAV-SN-002');
    $svc    = new ServiceTicketService();
    $ticket = $svc->open($unit, 'Battery issue');
    $svc->close($ticket, $unit);

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Closed);
    expect($ticket->closed_at)->not->toBeNull();

    $unit->refresh();
    expect($unit->status)->toBe(SerialStatus::Available);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// R3 — Closing an already-closed ticket throws DomainException
// ─────────────────────────────────────────────────────────────────────────────

it('close_throws_when_ticket_already_closed', function (): void {
    $tenant = Tenant::create(['name' => 'SAV Corp C', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $unit   = makeSavUnit('SAV-SN-003');
    $svc    = new ServiceTicketService();
    $ticket = $svc->open($unit, 'Charger port damaged');
    $svc->close($ticket, $unit);

    expect(fn () => $svc->close($ticket, $unit))->toThrow(\DomainException::class);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// R4 — A unit can accumulate multiple tickets over its lifetime
// ─────────────────────────────────────────────────────────────────────────────

it('unit_can_have_multiple_tickets_over_its_lifetime', function (): void {
    $tenant = Tenant::create(['name' => 'SAV Corp D', 'status' => 'actif']);
    tenancy()->initialize($tenant);

    $unit = makeSavUnit('SAV-SN-004');
    $svc  = new ServiceTicketService();

    $t1 = $svc->open($unit, 'First repair');
    $svc->close($t1, $unit);

    $t2 = $svc->open($unit, 'Second repair');
    $svc->close($t2, $unit);

    expect(ServiceTicket::where('serial_unit_id', $unit->id)->count())->toBe(2);

    tenancy()->end();
});

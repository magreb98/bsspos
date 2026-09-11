<?php

declare(strict_types=1);

/**
 * Sequential numbering tests — Task 0.5: Transversal foundation
 *
 * Decisions:
 *   D10 — PK (unit_id, key, fiscal_year): each store has its own counter per year
 *   D11 — SELECT ... FOR UPDATE inside the business transaction; PostgreSQL arbitrates concurrency
 */

use App\Control\Tenant;
use App\Platform\Sequencing\Actions\AllocateNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// T12 — Sequential numbers allocated without gaps
// ─────────────────────────────────────────────────────────────────────────────

it('allocates_sequential_numbers_without_gaps', function (): void {
    $tenant = Tenant::create([
        'name'   => 'Sequence SARL',
        'status' => 'actif',
    ]);

    tenancy()->initialize($tenant);

    $action  = new AllocateNumber();
    $unitId  = Uuid::uuid4();

    $action->initializeIfAbsent($unitId, 'sale', 2026);

    expect($action->allocate($unitId, 'sale', 2026))->toBe(1);
    expect($action->allocate($unitId, 'sale', 2026))->toBe(2);
    expect($action->allocate($unitId, 'sale', 2026))->toBe(3);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T13 — Counters isolated by unit and fiscal year
// ─────────────────────────────────────────────────────────────────────────────

it('isolates_counters_by_unit_and_fiscal_year', function (): void {
    $tenant = Tenant::create([
        'name'   => 'Double Unit Commerce',
        'status' => 'actif',
    ]);

    tenancy()->initialize($tenant);

    $action   = new AllocateNumber();
    $unitIdA  = Uuid::uuid4();
    $unitIdB  = Uuid::uuid4();

    $action->initializeIfAbsent($unitIdA, 'sale', 2026);
    $action->initializeIfAbsent($unitIdB, 'sale', 2026);

    expect($action->allocate($unitIdA, 'sale', 2026))->toBe(1);
    expect($action->allocate($unitIdB, 'sale', 2026))->toBe(1);

    expect($action->allocate($unitIdA, 'sale', 2026))->toBe(2);
    expect($action->allocate($unitIdB, 'sale', 2026))->toBe(2);

    tenancy()->end();
});

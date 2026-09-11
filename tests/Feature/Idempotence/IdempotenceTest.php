<?php

declare(strict_types=1);

/**
 * Idempotency tests — Task 0.5: Transversal foundation
 *
 * Decisions:
 *   D6 — Table `idempotency_keys` tenant-side; PK on `key` wins the PostgreSQL race
 *   D7 — Same key + different fingerprint → ConflictingRequestException (HTTP 422)
 */

use App\Control\Tenant;
use App\Platform\Idempotency\Actions\CheckIdempotence;
use App\Platform\Idempotency\Exceptions\ConflictingRequestException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// T8 — Reject a request with the same key but a different fingerprint
// ─────────────────────────────────────────────────────────────────────────────

it('rejects_request_with_same_key_different_fingerprint', function (): void {
    $tenant = Tenant::create([
        'name'   => 'Idempotence SARL',
        'status' => 'actif',
    ]);

    tenancy()->initialize($tenant);

    $action = new CheckIdempotence();

    $firstResult = $action->check('cmd-001', 'abc');
    expect($firstResult->isNew())->toBeTrue();

    $action->record('cmd-001', 'abc', ['id' => '123'], 200);

    expect(
        fn () => $action->check('cmd-001', 'xyz')
    )->toThrow(ConflictingRequestException::class);

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// T9 — Return the same response for an identical replay
// ─────────────────────────────────────────────────────────────────────────────

it('returns_same_response_for_identical_replay', function (): void {
    $tenant = Tenant::create([
        'name'   => 'Replay Corp',
        'status' => 'actif',
    ]);

    tenancy()->initialize($tenant);

    $action = new CheckIdempotence();

    $firstResult = $action->check('cmd-002', 'abc');
    expect($firstResult->isNew())->toBeTrue();

    $action->record('cmd-002', 'abc', ['id' => '456'], 200);

    $secondResult = $action->check('cmd-002', 'abc');

    expect($secondResult->isNew())->toBeFalse();
    expect($secondResult->response())->toBe(['id' => '456']);
    expect($secondResult->status())->toBe(200);
    expect(DB::table('idempotency_keys')->count())->toBe(1);

    tenancy()->end();
});

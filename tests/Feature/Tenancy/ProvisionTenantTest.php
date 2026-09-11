<?php

declare(strict_types=1);

/**
 * Tenant provisioning tests — Task 0.3: Provisioning and orchestration
 *
 * These tests are RED before the Coder's work (Action, Enum classes
 * and new columns do not exist yet).
 * They become GREEN once the Coder has completed task 0.3.
 *
 * Source of truth: socle-prompt/etat/conception-0.3.md
 *
 * Environment:
 *   - DB_CONNECTION=sqlite / DB_DATABASE=:memory: (phpunit.xml)
 *   - The landlord database runs on SQLite in-memory via RefreshDatabase.
 *   - T1 and T2 create a real temporary SQLite tenant database.
 *
 * Conventions:
 *   - Each test covers only one behaviour.
 *   - No conditional logic (if/else) in test bodies.
 *   - Names in English describing intent, not implementation.
 */

use App\Platform\Tenancy\Actions\ProvisionTenant;
use App\Platform\Tenancy\Enums\ProvisioningStep;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// T1 — full provisioning from zero to step 3
// ─────────────────────────────────────────────────────────────────────────────

test('it_provisions_a_tenant_from_zero_to_step_3', function (): void {
    // Arrange — a freshly created tenant with no provisioning
    $tenant = \App\Control\Tenant::create([
        'name'   => 'Acme SARL',
        'status' => 'actif',
    ]);

    // Ensure starting state
    expect($tenant->provisioning_step)->toBe(0);

    // Act — full provisioning from zero
    $action = new ProvisionTenant();
    $action->execute($tenant);

    // Assert — tenant advanced to full completion (step 5), with no error
    $tenant->refresh();

    expect($tenant->provisioning_step)->toBe(ProvisioningStep::SubscriptionOpened->value)
        ->and($tenant->provisioning_error)->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// T2 — idempotent resume from step 1 already completed
// ─────────────────────────────────────────────────────────────────────────────

test('it_resumes_provisioning_from_step_2_if_step_1_is_already_done', function (): void {
    // Arrange — tenant whose database was already created (step 1 already played)
    $tenant = \App\Control\Tenant::create([
        'name'              => 'Beta Corp',
        'status'            => 'actif',
        'provisioning_step' => ProvisioningStep::DatabaseCreated->value,
    ]);

    // Initialize tenant database to simulate it already exists
    tenancy()->initialize($tenant);
    tenancy()->end();

    // Act — resume without exception (step 1 must not be replayed)
    $action = new ProvisionTenant();

    // This call must not throw an exception even with the database already present
    expect(fn () => $action->execute($tenant))->not->toThrow(\Throwable::class);

    // Assert — provisioning advanced to full completion (step 5)
    $tenant->refresh();

    expect($tenant->provisioning_step)->toBe(ProvisioningStep::SubscriptionOpened->value)
        ->and($tenant->provisioning_error)->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// T3 — step 2 failure: current step preserved and error recorded
// ─────────────────────────────────────────────────────────────────────────────

test('it_writes_the_error_and_preserves_the_step_if_step_2_fails', function (): void {
    // Arrange — tenant that has passed step 1 but not yet step 2
    $tenant = \App\Control\Tenant::create([
        'name'              => 'Gamma SAS',
        'status'            => 'actif',
        'provisioning_step' => ProvisioningStep::DatabaseCreated->value,
    ]);

    // Anonymous subclass that simulates a step 2 failure (applying migrations)
    $actionWithFailure = new class () extends ProvisionTenant {
        protected function applyMigrations(\App\Control\Tenant $tenant): void
        {
            throw new \RuntimeException('Simulated tenant migration failure');
        }
    };

    // Act — provisioning must throw an exception and stop at step 1
    expect(fn () => $actionWithFailure->execute($tenant))->toThrow(\RuntimeException::class);

    // Assert — step stays at 1, error is recorded
    $tenant->refresh();

    expect($tenant->provisioning_step)->toBe(ProvisioningStep::DatabaseCreated->value)
        ->and($tenant->provisioning_error)->not->toBeNull()
        ->and($tenant->provisioning_error)->toContain('Simulated tenant migration failure');
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — idempotency: no action if provisioning already complete
// ─────────────────────────────────────────────────────────────────────────────

test('it_does_not_replay_a_completely_finished_action', function (): void {
    // Arrange — tenant already at the final provisioning step (step 5)
    $tenant = \App\Control\Tenant::create([
        'name'               => 'Delta Inc',
        'status'             => 'actif',
        'provisioning_step'  => ProvisioningStep::SubscriptionOpened->value,
        'provisioning_error' => null,
    ]);

    // Act — no-op call: no step must be replayed
    $action = new ProvisionTenant();
    $action->execute($tenant);

    // Assert — step and error remain unchanged
    $tenant->refresh();

    expect($tenant->provisioning_step)->toBe(ProvisioningStep::SubscriptionOpened->value)
        ->and($tenant->provisioning_error)->toBeNull();
});

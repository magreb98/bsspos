<?php

declare(strict_types=1);

/**
 * Unit tests for Granularity enum — Task 1.2
 *
 * Decision D3 — Granularity: string-backed enum with 5 cases
 */

use Modules\Commerce\Internal\Enums\Granularity;

// ─────────────────────────────────────────────────────────────────────────────
// T5 — Granularity::Service value is 'service'
// ─────────────────────────────────────────────────────────────────────────────

it('granularity_service_has_value_service', function (): void {
    expect(Granularity::Service->value)->toBe('service');
});

it('granularity_enum_has_five_cases', function (): void {
    expect(Granularity::cases())->toHaveCount(5);
});

it('granularity_can_be_created_from_string', function (): void {
    expect(Granularity::from('quantity'))->toBe(Granularity::Quantity);
    expect(Granularity::from('service'))->toBe(Granularity::Service);
});

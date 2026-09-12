<?php

declare(strict_types=1);

/**
 * Unit tests for VatCalculator — pure-integer conversion of a decimal VAT
 * rate into basis points (e.g. "19.25" -> 1925).
 *
 * Uses bcmath (via bcmul with scale 0) instead of a float cast so that
 * decimal rates never pick up binary floating-point imprecision on their
 * way into a monetary calculation.
 */

use Modules\Commerce\Internal\Support\VatCalculator;

// ─────────────────────────────────────────────────────────────────────────────
// Typical decimal string rates
// ─────────────────────────────────────────────────────────────────────────────

it('converts_a_typical_decimal_string_rate_to_basis_points', function (): void {
    expect(VatCalculator::basisPoints('19.25'))->toBe(1925);
});

it('converts_a_whole_percent_string_rate_to_basis_points', function (): void {
    expect(VatCalculator::basisPoints('18'))->toBe(1800);
});

// ─────────────────────────────────────────────────────────────────────────────
// Float input avoids binary floating-point imprecision
// ─────────────────────────────────────────────────────────────────────────────

it('converts_a_float_rate_to_basis_points_without_floating_point_drift', function (): void {
    expect(VatCalculator::basisPoints(19.25))->toBe(1925);
});

it('converts_a_whole_float_rate_to_basis_points', function (): void {
    expect(VatCalculator::basisPoints(100.0))->toBe(10000);
});

// ─────────────────────────────────────────────────────────────────────────────
// Integer input
// ─────────────────────────────────────────────────────────────────────────────

it('converts_an_integer_rate_to_basis_points', function (): void {
    expect(VatCalculator::basisPoints(19))->toBe(1900);
});

it('converts_a_zero_integer_rate_to_zero_basis_points', function (): void {
    expect(VatCalculator::basisPoints(0))->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Zero / blank defensive defaults
// ─────────────────────────────────────────────────────────────────────────────

it('converts_a_zero_decimal_string_to_zero_basis_points', function (): void {
    expect(VatCalculator::basisPoints('0.00'))->toBe(0);
});

it('treats_an_empty_string_rate_as_zero', function (): void {
    expect(VatCalculator::basisPoints(''))->toBe(0);
});

it('treats_a_lone_dash_rate_as_zero', function (): void {
    expect(VatCalculator::basisPoints('-'))->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// Whitespace tolerance
// ─────────────────────────────────────────────────────────────────────────────

it('trims_surrounding_whitespace_from_a_string_rate', function (): void {
    expect(VatCalculator::basisPoints(' 19.25 '))->toBe(1925);
});

// ─────────────────────────────────────────────────────────────────────────────
// Truncation (not rounding) beyond basis-point precision
// ─────────────────────────────────────────────────────────────────────────────

it('truncates_sub_basis_point_precision_instead_of_rounding', function (): void {
    // 19.999 * 100 = 1999.9 -> truncates to 1999, never rounds up to 2000
    expect(VatCalculator::basisPoints('19.999'))->toBe(1999);
});

// ─────────────────────────────────────────────────────────────────────────────
// Negative rates are preserved (e.g. credit-note reversal scenarios)
// ─────────────────────────────────────────────────────────────────────────────

it('preserves_the_sign_of_a_negative_rate', function (): void {
    expect(VatCalculator::basisPoints('-5.5'))->toBe(-550);
});

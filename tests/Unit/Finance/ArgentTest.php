<?php

declare(strict_types=1);

/**
 * Unit tests for the Amount value object — Task 0.5: Transversal foundation
 *
 * Decisions:
 *   D4 — CURRENCY = 'XAF' constant in Amount; brick/money eliminates mixed currencies
 *   D5 — bigint storage in integer francs (0 decimals); no rounding on read
 */

use App\Platform\Money\Amount;

// ─────────────────────────────────────────────────────────────────────────────
// T3 — Create an amount from an integer
// ─────────────────────────────────────────────────────────────────────────────

it('creates_amount_from_int', function (): void {
    expect(Amount::fromInt(10000)->toInt())->toBe(10000);
});

// ─────────────────────────────────────────────────────────────────────────────
// T4 — Addition of two amounts
// ─────────────────────────────────────────────────────────────────────────────

it('adds_two_amounts', function (): void {
    $result = Amount::fromInt(100)->add(Amount::fromInt(50));

    expect($result->toInt())->toBe(150);
});

// ─────────────────────────────────────────────────────────────────────────────
// T5 — Subtraction of two amounts
// ─────────────────────────────────────────────────────────────────────────────

it('subtracts_two_amounts', function (): void {
    $result = Amount::fromInt(100)->subtract(Amount::fromInt(30));

    expect($result->toInt())->toBe(70);
});

// ─────────────────────────────────────────────────────────────────────────────
// T6 — Multiplication by an integer
// ─────────────────────────────────────────────────────────────────────────────

it('multiplies_by_integer', function (): void {
    $result = Amount::fromInt(100)->multiplyBy(3);

    expect($result->toInt())->toBe(300);
});

// ─────────────────────────────────────────────────────────────────────────────
// T7 — brick/money raises an exception on impossible rounding
//
// Proves that brick/money is configured with RoundingMode::UNNECESSARY:
// dividing 100 XAF by 3 is mathematically impossible without a remainder —
// brick/money raises RoundingNecessaryException.
//
// This enforces D4/Rule 4: XAF has no subdivision, so any operation producing
// a non-integer fraction is a programming error, never silently rounded.
// ─────────────────────────────────────────────────────────────────────────────

it('raises_exception_on_impossible_rounding', function (): void {
    expect(
        fn () => \Brick\Money\Money::of(100, 'XAF')
            ->dividedBy(3, \Brick\Math\RoundingMode::UNNECESSARY)
    )->toThrow(\Brick\Math\Exception\RoundingNecessaryException::class);
});

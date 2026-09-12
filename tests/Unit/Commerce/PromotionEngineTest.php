<?php

declare(strict_types=1);

/**
 * Unit tests for PromotionEngine — discount computation and promotion/coupon
 * scoping rules.
 *
 * PromotionEngine::apply() itself is NOT unit-testable in isolation: it reads
 * sale lines and active promotions through Eloquent queries and persists the
 * result via $line->update(), all of which require a booted Laravel
 * application and a real database connection. tests/Unit does not boot the
 * framework (tests/Pest.php only binds Tests\TestCase to Feature/Architecture),
 * so apply() is left to the Feature test suite.
 *
 * The methods exercised below are the engine's actual money/rule logic:
 * discount rounding, cumulative vs. competing (non-cumulative) promotions,
 * capping the discount at the gross amount, and product/family scope
 * matching. They are private, so ReflectionMethod is used to invoke them
 * directly on plain, unsaved Eloquent model instances built in memory — no
 * query builder method is ever called on these models, so no database
 * connection is touched.
 */

use Modules\Commerce\Internal\Enums\PromotionScope;
use Modules\Commerce\Internal\Enums\PromotionType;
use Modules\Commerce\Internal\Models\Coupon;
use Modules\Commerce\Internal\Models\Product;
use Modules\Commerce\Internal\Models\Promotion;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\Commerce\Internal\Services\PromotionEngine;

/**
 * @param  list<mixed>  $args
 */
function promotionEngineReflect(string $method, array $args): mixed
{
    $engine     = new PromotionEngine();
    $reflection = new ReflectionMethod(PromotionEngine::class, $method);

    return $reflection->invokeArgs($engine, $args);
}

function makeTestPromotion(
    PromotionType $type,
    int $value,
    bool $cumulative = false,
    PromotionScope $scope = PromotionScope::Product,
    ?string $scopeId = 'product-1',
    ?string $id = null,
): Promotion {
    $promotion = new Promotion([
        'type'       => $type,
        'value'      => $value,
        'scope'      => $scope,
        'scope_id'   => $scopeId,
        'cumulative' => $cumulative,
        'active'     => true,
    ]);
    $promotion->id = $id ?? ('promo-' . spl_object_id($promotion));

    return $promotion;
}

function makeTestSaleLine(int $unitPrice, int $quantity, ?string $productId = 'product-1'): SaleLine
{
    return new SaleLine([
        'product_id' => $productId,
        'unit_price' => $unitPrice,
        'quantity'   => $quantity,
        'vat_rate'   => '19.25',
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// discountAmount() — percent (basis points) vs. fixed amount
// ─────────────────────────────────────────────────────────────────────────────

it('computes_a_percentage_discount_from_basis_points', function (): void {
    $promotion = makeTestPromotion(PromotionType::Percent, 1000); // 10.00%

    expect(promotionEngineReflect('discountAmount', [10000, $promotion]))->toBe(1000);
});

it('rounds_a_percentage_discount_to_the_nearest_franc', function (): void {
    $promotion = makeTestPromotion(PromotionType::Percent, 3333); // 33.33%

    // 1000 * 3333 / 10000 = 333.3 -> rounds to 333
    expect(promotionEngineReflect('discountAmount', [1000, $promotion]))->toBe(333);
});

it('applies_a_zero_percent_discount_as_zero', function (): void {
    $promotion = makeTestPromotion(PromotionType::Percent, 0);

    expect(promotionEngineReflect('discountAmount', [10000, $promotion]))->toBe(0);
});

it('applies_a_full_hundred_percent_discount_equal_to_gross', function (): void {
    $promotion = makeTestPromotion(PromotionType::Percent, 10000); // 100.00%

    expect(promotionEngineReflect('discountAmount', [5000, $promotion]))->toBe(5000);
});

it('returns_the_flat_value_for_a_fixed_amount_discount_regardless_of_gross', function (): void {
    $promotion = makeTestPromotion(PromotionType::FixedAmount, 1500);

    expect(promotionEngineReflect('discountAmount', [10000, $promotion]))->toBe(1500);
    expect(promotionEngineReflect('discountAmount', [500, $promotion]))->toBe(1500);
});

// ─────────────────────────────────────────────────────────────────────────────
// computeDiscount() — cumulative stacking vs. non-cumulative competition, cap
// ─────────────────────────────────────────────────────────────────────────────

it('returns_zero_discount_when_no_promotion_applies', function (): void {
    $line = makeTestSaleLine(unitPrice: 1000, quantity: 1);

    expect(promotionEngineReflect('computeDiscount', [$line, []]))->toBe(0);
});

it('stacks_cumulative_discounts', function (): void {
    $line = makeTestSaleLine(unitPrice: 10000, quantity: 1); // gross 10 000

    $promotions = [
        makeTestPromotion(PromotionType::Percent, 1000, cumulative: true), // 10% -> 1000
        makeTestPromotion(PromotionType::Percent, 500, cumulative: true),  // 5%  -> 500
    ];

    expect(promotionEngineReflect('computeDiscount', [$line, $promotions]))->toBe(1500);
});

it('picks_the_single_best_discount_when_a_non_cumulative_promotion_competes', function (): void {
    $line = makeTestSaleLine(unitPrice: 10000, quantity: 1); // gross 10 000

    $promotions = [
        makeTestPromotion(PromotionType::Percent, 2000, cumulative: false),    // 20% -> 2000
        makeTestPromotion(PromotionType::FixedAmount, 5000, cumulative: true), // 5000 — competes, does not stack
    ];

    expect(promotionEngineReflect('computeDiscount', [$line, $promotions]))->toBe(5000);
});

it('computes_the_gross_amount_from_unit_price_times_quantity', function (): void {
    $line = makeTestSaleLine(unitPrice: 2500, quantity: 4); // gross 10 000

    $promotions = [makeTestPromotion(PromotionType::Percent, 1000, cumulative: true)]; // 10%

    expect(promotionEngineReflect('computeDiscount', [$line, $promotions]))->toBe(1000);
});

it('caps_the_stacked_cumulative_discount_at_the_gross_amount', function (): void {
    $line = makeTestSaleLine(unitPrice: 1000, quantity: 1); // gross 1000

    $promotions = [
        makeTestPromotion(PromotionType::FixedAmount, 800, cumulative: true),
        makeTestPromotion(PromotionType::FixedAmount, 800, cumulative: true),
    ];

    // 800 + 800 = 1600, capped at the gross of 1000
    expect(promotionEngineReflect('computeDiscount', [$line, $promotions]))->toBe(1000);
});

it('caps_a_single_competing_discount_at_the_gross_amount', function (): void {
    $line = makeTestSaleLine(unitPrice: 1000, quantity: 1); // gross 1000

    $promotions = [makeTestPromotion(PromotionType::FixedAmount, 5000, cumulative: false)];

    expect(promotionEngineReflect('computeDiscount', [$line, $promotions]))->toBe(1000);
});

it('applies_zero_discount_on_a_zero_gross_line', function (): void {
    $line = makeTestSaleLine(unitPrice: 0, quantity: 3); // gross 0

    $promotions = [makeTestPromotion(PromotionType::FixedAmount, 5000, cumulative: true)];

    expect(promotionEngineReflect('computeDiscount', [$line, $promotions]))->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// promotionAppliesToLine() — product vs. family scope
// ─────────────────────────────────────────────────────────────────────────────

it('matches_a_product_scoped_promotion_by_product_id', function (): void {
    $line      = makeTestSaleLine(unitPrice: 1000, quantity: 1, productId: 'product-1');
    $promotion = makeTestPromotion(PromotionType::Percent, 1000, scope: PromotionScope::Product, scopeId: 'product-1');

    expect(promotionEngineReflect('promotionAppliesToLine', [$line, $promotion]))->toBeTrue();
});

it('does_not_match_a_product_scoped_promotion_for_a_different_product', function (): void {
    $line      = makeTestSaleLine(unitPrice: 1000, quantity: 1, productId: 'product-1');
    $promotion = makeTestPromotion(PromotionType::Percent, 1000, scope: PromotionScope::Product, scopeId: 'product-2');

    expect(promotionEngineReflect('promotionAppliesToLine', [$line, $promotion]))->toBeFalse();
});

it('matches_a_family_scoped_promotion_through_the_line_product_relation', function (): void {
    $line = makeTestSaleLine(unitPrice: 1000, quantity: 1);
    $line->setRelation('product', new Product(['family_id' => 'family-1']));

    $promotion = makeTestPromotion(PromotionType::Percent, 1000, scope: PromotionScope::Family, scopeId: 'family-1');

    expect(promotionEngineReflect('promotionAppliesToLine', [$line, $promotion]))->toBeTrue();
});

it('does_not_match_a_family_scoped_promotion_for_a_different_family', function (): void {
    $line = makeTestSaleLine(unitPrice: 1000, quantity: 1);
    $line->setRelation('product', new Product(['family_id' => 'family-1']));

    $promotion = makeTestPromotion(PromotionType::Percent, 1000, scope: PromotionScope::Family, scopeId: 'family-2');

    expect(promotionEngineReflect('promotionAppliesToLine', [$line, $promotion]))->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// couponAppliesToLine() — delegates to the coupon's linked promotion scope
// ─────────────────────────────────────────────────────────────────────────────

it('applies_a_coupon_when_its_promotion_scope_matches_the_line', function (): void {
    $line      = makeTestSaleLine(unitPrice: 1000, quantity: 1, productId: 'product-1');
    $promotion = makeTestPromotion(PromotionType::Percent, 1000, scope: PromotionScope::Product, scopeId: 'product-1');

    $coupon = new Coupon(['code' => 'SAVE10']);
    $coupon->setRelation('promotion', $promotion);

    expect(promotionEngineReflect('couponAppliesToLine', [$line, $coupon]))->toBeTrue();
});

it('does_not_apply_a_coupon_with_no_linked_promotion', function (): void {
    $line = makeTestSaleLine(unitPrice: 1000, quantity: 1, productId: 'product-1');

    $coupon = new Coupon(['code' => 'SAVE10']);
    $coupon->setRelation('promotion', null);

    expect(promotionEngineReflect('couponAppliesToLine', [$line, $coupon]))->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// applicablePromotions() — coupon/active-promotion de-duplication
// ─────────────────────────────────────────────────────────────────────────────

it('does_not_duplicate_a_promotion_already_active_when_its_coupon_also_targets_it', function (): void {
    $line      = makeTestSaleLine(unitPrice: 1000, quantity: 1, productId: 'product-1');
    $promotion = makeTestPromotion(
        PromotionType::Percent,
        1000,
        scope: PromotionScope::Product,
        scopeId: 'product-1',
        id: 'promo-shared',
    );

    $coupon = new Coupon(['code' => 'SAVE10', 'promotion_id' => 'promo-shared']);
    $coupon->setRelation('promotion', $promotion);

    $result = promotionEngineReflect('applicablePromotions', [$line, [$promotion], $coupon]);

    expect($result)->toHaveCount(1);
});

it('adds_the_coupon_promotion_when_it_is_not_already_among_the_active_promotions', function (): void {
    $line = makeTestSaleLine(unitPrice: 1000, quantity: 1, productId: 'product-1');

    $couponPromotion = makeTestPromotion(
        PromotionType::FixedAmount,
        500,
        scope: PromotionScope::Product,
        scopeId: 'product-1',
        id: 'promo-coupon-only',
    );
    $coupon = new Coupon(['code' => 'SAVE5', 'promotion_id' => 'promo-coupon-only']);
    $coupon->setRelation('promotion', $couponPromotion);

    $result = promotionEngineReflect('applicablePromotions', [$line, [], $coupon]);

    expect($result)->toHaveCount(1);
    expect($result[0])->toBe($couponPromotion);
});

it('ignores_a_coupon_whose_promotion_does_not_apply_to_the_line', function (): void {
    $line = makeTestSaleLine(unitPrice: 1000, quantity: 1, productId: 'product-1');

    $couponPromotion = makeTestPromotion(
        PromotionType::FixedAmount,
        500,
        scope: PromotionScope::Product,
        scopeId: 'product-2', // different product — coupon should not apply
        id: 'promo-other-product',
    );
    $coupon = new Coupon(['code' => 'SAVE5', 'promotion_id' => 'promo-other-product']);
    $coupon->setRelation('promotion', $couponPromotion);

    $result = promotionEngineReflect('applicablePromotions', [$line, [], $coupon]);

    expect($result)->toBe([]);
});

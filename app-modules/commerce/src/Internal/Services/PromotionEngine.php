<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use Modules\Commerce\Internal\Enums\PromotionScope;
use Modules\Commerce\Internal\Enums\PromotionType;
use Modules\Commerce\Internal\Models\Coupon;
use Modules\Commerce\Internal\Models\Promotion;
use Modules\Commerce\Internal\Models\Sale;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\Commerce\Internal\Support\VatCalculator;

final class PromotionEngine
{
    /**
     * Apply active promotions and an optional coupon to all draft sale lines.
     * Must be called inside the caller's DB::transaction().
     *
     * @throws \DomainException when the coupon code is unknown or exhausted
     */
    public function apply(Sale $sale, ?string $couponCode = null): void
    {
        $now    = now();
        $lines  = $sale->lines()->with('product.family')->get();
        $coupon = $this->resolveCoupon($couponCode);

        /** @var list<Promotion> $promotions */
        $promotions = Promotion::query()
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->get()
            ->values()
            ->all();

        foreach ($lines as $line) {
            $applicable = $this->applicablePromotions($line, $promotions, $coupon);
            $discount   = $this->computeDiscount($line, $applicable);

            if ($discount === 0 && $coupon === null) {
                continue;
            }

            $grossHt = $line->unit_price?->toInt() ?? 0;
            $grossHt = $grossHt * $line->quantity;
            $netHt   = $grossHt - $discount;
            $vatBp   = VatCalculator::basisPoints((string) $line->vat_rate);
            $tax     = (int) round($netHt * $vatBp / 10000);
            $ttc     = $netHt + $tax;

            $line->update([
                'discount_amount'          => $discount,
                'coupon_id'                => ($coupon !== null && $this->couponAppliesToLine($line, $coupon)) ? $coupon->id : null,
                'line_total_excluding_tax' => $netHt,
                'line_total_tax'           => $tax,
                'line_total_including_tax' => $ttc,
            ]);
        }

        if ($coupon !== null) {
            $coupon->increment('times_used');
        }
    }

    /**
     * @param list<Promotion> $promotions
     * @return list<Promotion>
     */
    private function applicablePromotions(SaleLine $line, array $promotions, ?Coupon $coupon): array
    {
        /** @var list<Promotion> $result */
        $result = [];

        foreach ($promotions as $promotion) {
            if ($this->promotionAppliesToLine($line, $promotion)) {
                $result[] = $promotion;
            }
        }

        if ($coupon !== null && $this->couponAppliesToLine($line, $coupon)) {
            $couponPromoId = $coupon->promotion_id;
            $alreadyIn     = false;
            foreach ($result as $p) {
                if ($p->id === $couponPromoId) {
                    $alreadyIn = true;
                    break;
                }
            }
            if (! $alreadyIn && $coupon->promotion !== null) {
                $result[] = $coupon->promotion;
            }
        }

        return $result;
    }

    /** @param list<Promotion> $applicable */
    private function computeDiscount(SaleLine $line, array $applicable): int
    {
        if ($applicable === []) {
            return 0;
        }

        $grossHt     = ($line->unit_price?->toInt() ?? 0) * $line->quantity;
        $hasCumul    = false;
        $hasNonCumul = false;

        foreach ($applicable as $p) {
            if ($p->cumulative) {
                $hasCumul = true;
            } else {
                $hasNonCumul = true;
            }
        }

        // Cumulative promotions stack; non-cumulative ones compete (best wins)
        if ($hasNonCumul) {
            $best = 0;
            foreach ($applicable as $p) {
                $d    = $this->discountAmount($grossHt, $p);
                $best = max($best, $d);
            }

            return min($best, $grossHt);
        }

        // All cumulative — stack them
        $total = 0;
        foreach ($applicable as $p) {
            $total += $this->discountAmount($grossHt, $p);
        }

        return min($total, $grossHt);
    }

    private function discountAmount(int $grossHt, Promotion $promotion): int
    {
        return match ($promotion->type) {
            PromotionType::Percent     => (int) round($grossHt * $promotion->value / 10000),
            PromotionType::FixedAmount => $promotion->value,
        };
    }

    private function promotionAppliesToLine(SaleLine $line, Promotion $promotion): bool
    {
        return match ($promotion->scope) {
            PromotionScope::Product => $line->product_id === $promotion->scope_id,
            PromotionScope::Family  => $line->product?->family_id === $promotion->scope_id,
        };
    }

    private function couponAppliesToLine(SaleLine $line, Coupon $coupon): bool
    {
        $promotion = $coupon->promotion;

        if ($promotion === null) {
            return false;
        }

        return $this->promotionAppliesToLine($line, $promotion);
    }

    /** @throws \DomainException */
    private function resolveCoupon(?string $code): ?Coupon
    {
        if ($code === null) {
            return null;
        }

        $coupon = Coupon::with('promotion')->where('code', $code)->first();

        if ($coupon === null) {
            throw new \DomainException("Coupon '{$code}' not found.");
        }

        if ($coupon->isExhausted()) {
            throw new \DomainException("Coupon '{$code}' has reached its usage limit.");
        }

        return $coupon;
    }
}

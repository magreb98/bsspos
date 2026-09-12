<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Http\Requests\StoreCouponRequest;
use Modules\Commerce\Http\Requests\StorePromotionRequest;
use Modules\Commerce\Http\Requests\UpdatePromotionRequest;
use Modules\Commerce\Http\Requests\ValidateCouponRequest;
use Modules\Commerce\Http\Resources\CouponResource;
use Modules\Commerce\Http\Resources\PromotionResource;
use Modules\Commerce\Internal\Models\Coupon;
use Modules\Commerce\Internal\Models\Promotion;

final class PromotionController
{
    public function index(Request $request): JsonResponse
    {
        $query = Promotion::query()->with('coupons');

        if (! $request->boolean('include_inactive', false)) {
            $query->where('active', true);
        }

        $promotions = $query->orderBy('starts_at')->get();

        return response()->json(['data' => PromotionResource::collection($promotions)]);
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $promotion = Promotion::create(array_merge($validated, [
            'cumulative' => $validated['cumulative'] ?? false,
            'active'     => $validated['active'] ?? true,
        ]));

        return response()->json(['data' => new PromotionResource($promotion)], 201);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): JsonResponse
    {
        $validated = $request->validated();

        $promotion->update($validated);

        return response()->json(['data' => new PromotionResource($promotion->fresh() ?? $promotion)]);
    }

    public function validateCoupon(ValidateCouponRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $coupon = Coupon::query()
            ->where('code', $validated['code'])
            ->with('promotion')
            ->first();

        if ($coupon === null) {
            return response()->json(['code' => 'COUPON_NOT_FOUND', 'message' => 'Ce code promo n\'existe pas.', 'champ' => 'code'], 404);
        }

        if ($coupon->isExhausted()) {
            return response()->json(['code' => 'COUPON_EXHAUSTED', 'message' => 'Ce coupon a atteint son nombre maximal d\'utilisations.', 'champ' => 'code'], 409);
        }

        $promotion = $coupon->promotion;

        if ($promotion === null || ! $promotion->isActiveAt(now())) {
            return response()->json(['code' => 'PROMOTION_INACTIVE', 'message' => 'La promotion associée à ce coupon n\'est pas active.', 'champ' => 'code'], 409);
        }

        return response()->json([
            'data' => [
                'coupon'    => new CouponResource($coupon),
                'promotion' => new PromotionResource($promotion),
                'valid'     => true,
            ],
        ]);
    }

    public function storeCoupon(StoreCouponRequest $request, Promotion $promotion): JsonResponse
    {
        $validated = $request->validated();

        $coupon = Coupon::create([
            'code'         => strtoupper($validated['code']),
            'promotion_id' => $promotion->id,
            'max_uses'     => $validated['max_uses'] ?? null,
            'times_used'   => 0,
        ]);

        return response()->json(['data' => new CouponResource($coupon)], 201);
    }
}

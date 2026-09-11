<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json(['data' => $promotions->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'type'       => ['required', 'string', 'in:percent,fixed_amount'],
            'value'      => ['required', 'integer', 'min:1'],
            'scope'      => ['required', 'string', 'in:product,family'],
            'scope_id'   => ['sometimes', 'nullable', 'uuid'],
            'starts_at'  => ['sometimes', 'nullable', 'date'],
            'ends_at'    => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'cumulative' => ['sometimes', 'boolean'],
            'active'     => ['sometimes', 'boolean'],
        ]);

        $promotion = Promotion::create(array_merge($validated, [
            'cumulative' => $validated['cumulative'] ?? false,
            'active'     => $validated['active'] ?? true,
        ]));

        return response()->json(['data' => $promotion->toArray()], 201);
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $validated = $request->validate([
            'name'       => ['sometimes', 'string', 'max:255'],
            'value'      => ['sometimes', 'integer', 'min:1'],
            'starts_at'  => ['sometimes', 'nullable', 'date'],
            'ends_at'    => ['sometimes', 'nullable', 'date'],
            'cumulative' => ['sometimes', 'boolean'],
            'active'     => ['sometimes', 'boolean'],
        ]);

        $promotion->update($validated);

        return response()->json(['data' => $promotion->fresh()?->toArray() ?? $promotion->toArray()]);
    }

    public function validateCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

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
                'coupon'    => $coupon->toArray(),
                'promotion' => $promotion->toArray(),
                'valid'     => true,
            ],
        ]);
    }

    public function storeCoupon(Request $request, Promotion $promotion): JsonResponse
    {
        $validated = $request->validate([
            'code'     => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'max_uses' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $coupon = Coupon::create([
            'code'         => strtoupper($validated['code']),
            'promotion_id' => $promotion->id,
            'max_uses'     => $validated['max_uses'] ?? null,
            'times_used'   => 0,
        ]);

        return response()->json(['data' => $coupon->toArray()], 201);
    }
}

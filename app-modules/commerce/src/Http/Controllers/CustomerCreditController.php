<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Commerce\Http\Resources\CustomerCreditResource;
use Modules\Commerce\Internal\Models\Customer;
use Modules\Commerce\Internal\Models\CustomerCredit;

final class CustomerCreditController
{
    public function index(Customer $customer): JsonResponse
    {
        $credits = CustomerCredit::where('customer_id', $customer->id)
            ->where('remaining_amount', '>', 0)
            ->orderBy('created_at')
            ->get();

        $totalRemaining = $credits->sum(fn (CustomerCredit $c): int => $c->remaining_amount?->toInt() ?? 0);

        return response()->json([
            'data' => [
                'credits'         => CustomerCreditResource::collection($credits),
                'total_remaining' => $totalRemaining,
            ],
        ]);
    }
}

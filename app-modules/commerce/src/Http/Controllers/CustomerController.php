<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Http\Requests\StoreCustomerRequest;
use Modules\Commerce\Http\Requests\UpdateCustomerRequest;
use Modules\Commerce\Http\Resources\CustomerResource;
use Modules\Commerce\Http\Resources\SaleResource;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Models\Customer;
use Modules\Commerce\Internal\Models\Sale;

final class CustomerController
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        if ($request->filled('search')) {
            $term = (string) $request->input('search');
            $query->where(static function ($q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        $customers = $query->orderBy('name')->get();

        return response()->json(['data' => CustomerResource::collection($customers)]);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $salesCount = Sale::query()
            ->where('client_id', $customer->id)
            ->where('state', SaleState::Confirmed)
            ->count();

        $totalSpent = (int) Sale::query()
            ->where('client_id', $customer->id)
            ->where('state', SaleState::Confirmed)
            ->sum('total_including_tax');

        return response()->json([
            'data' => array_merge((new CustomerResource($customer))->resolve($request), [
                'total_spent' => $totalSpent,
                'sales_count' => $salesCount,
            ]),
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $customer = Customer::create(array_merge($validated, [
            'outstanding_balance' => 0,
            'active'              => true,
        ]));

        return response()->json(['data' => new CustomerResource($customer)], 201);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $validated = $request->validated();

        $customer->update($validated);

        return response()->json(['data' => new CustomerResource($customer->fresh() ?? $customer)]);
    }

    public function sales(Request $request, Customer $customer): JsonResponse
    {
        $query = Sale::query()
            ->where('client_id', $customer->id)
            ->with('lines')
            ->latest('created_at');

        if ($request->filled('state')) {
            $query->where('state', $request->input('state'));
        }

        $paginator = $query->paginate(20);

        return response()->json([
            'data' => SaleResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }
}

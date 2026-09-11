<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json(['data' => $customers->toArray()]);
    }

    public function show(Customer $customer): JsonResponse
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
            'data' => array_merge($customer->toArray(), [
                'total_spent' => $totalSpent,
                'sales_count' => $salesCount,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'phone'        => ['sometimes', 'nullable', 'string', 'max:30'],
            'credit_limit' => ['sometimes', 'integer', 'min:0'],
        ]);

        $customer = Customer::create(array_merge($validated, [
            'outstanding_balance' => 0,
            'active'              => true,
        ]));

        return response()->json(['data' => $customer->toArray()], 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name'         => ['sometimes', 'string', 'max:255'],
            'phone'        => ['sometimes', 'nullable', 'string', 'max:30'],
            'credit_limit' => ['sometimes', 'integer', 'min:0'],
            'active'       => ['sometimes', 'boolean'],
        ]);

        $customer->update($validated);

        return response()->json(['data' => $customer->fresh()?->toArray() ?? $customer->toArray()]);
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
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }
}

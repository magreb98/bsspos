<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Http\Requests\StoreExpenseRequest;
use Modules\Commerce\Http\Resources\ExpenseResource;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Expense;

final class ExpenseController
{
    public function index(Request $request): JsonResponse
    {
        $query = Expense::latest();

        if ($request->filled('cash_session_id')) {
            $query->where('cash_session_id', $request->input('cash_session_id'));
        }

        return response()->json(['data' => ExpenseResource::collection($query->get())]);
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $data = $request->validated();

        $session = CashSession::where('id', $data['cash_session_id'])->firstOrFail();

        if (! $session->isOpen()) {
            return response()->json(['code' => 'SESSION_CLOSED', 'message' => 'La session de caisse est fermée.'], 409);
        }

        $expense = Expense::create([
            'cash_session_id' => $session->id,
            'amount'          => $data['amount'],
            'label'           => $data['label'],
            'recorded_at'     => now(),
        ]);

        return response()->json(['data' => new ExpenseResource($expense)], 201);
    }
}

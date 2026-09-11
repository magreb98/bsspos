<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cash_session_id' => 'required|uuid',
            'amount'          => 'required|integer|min:1',
            'label'           => 'required|string|max:255',
        ]);

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

        return response()->json(['data' => $expense], 201);
    }
}

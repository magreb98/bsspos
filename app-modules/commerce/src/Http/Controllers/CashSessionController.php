<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashRegister;
use Modules\Commerce\Internal\Models\CashSession;

final class CashSessionController
{
    public function index(Request $request): JsonResponse
    {
        $query = CashSession::query()->with('cashRegister.pointOfSale')->latest('opened_at');

        if ($request->filled('cash_register_id')) {
            $query->where('cash_register_id', $request->input('cash_register_id'));
        }

        if ($request->filled('state')) {
            $query->where('state', $request->input('state'));
        }

        if ($request->filled('from')) {
            $query->whereDate('opened_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('opened_at', '<=', $request->input('to'));
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cash_register_id' => ['required', 'uuid'],
            'opening_balance'  => ['required', 'integer', 'min:0'],
        ]);

        $register = CashRegister::query()->whereKey($validated['cash_register_id'])->first();
        if ($register === null) {
            return response()->json(['code' => 'REGISTER_NOT_FOUND', 'message' => 'Caisse introuvable.', 'champ' => 'cash_register_id'], 404);
        }

        $existing = CashSession::query()
            ->where('cash_register_id', $register->id)
            ->where('state', SessionState::Open)
            ->first();

        if ($existing !== null) {
            return response()->json(['code' => 'SESSION_ALREADY_OPEN', 'message' => 'Une session est déjà ouverte sur cette caisse.', 'champ' => null], 409);
        }

        $userId = (string) $request->user()?->getAuthIdentifier();

        $session = CashSession::create([
            'cash_register_id' => $register->id,
            'state'            => SessionState::Open,
            'opened_at'        => now(),
            'opening_balance'  => $validated['opening_balance'],
            'opened_by'        => $userId,
        ]);

        return response()->json(['data' => $session->toArray()], 201);
    }

    public function showActive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cash_register_id' => ['required', 'uuid'],
        ]);

        $session = CashSession::query()
            ->where('cash_register_id', $validated['cash_register_id'])
            ->where('state', SessionState::Open)
            ->first();

        if ($session === null) {
            return response()->json(['code' => 'NO_ACTIVE_SESSION', 'message' => 'Aucune session ouverte.', 'champ' => null], 404);
        }

        return response()->json(['data' => $session->toArray()]);
    }

    public function close(Request $request, CashSession $cash_session): JsonResponse
    {
        if (! $cash_session->isOpen()) {
            return response()->json(['code' => 'SESSION_NOT_OPEN', 'message' => 'Cette session n\'est pas ouverte.', 'champ' => null], 409);
        }

        $validated = $request->validate([
            'closing_balance' => ['required', 'integer', 'min:0'],
        ]);

        $userId = (string) $request->user()?->getAuthIdentifier();
        $cash_session->close($userId, $validated['closing_balance']);

        return response()->json(['data' => $cash_session->fresh()?->toArray() ?? $cash_session->toArray()]);
    }
}

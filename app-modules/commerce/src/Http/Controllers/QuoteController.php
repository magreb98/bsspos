<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Commerce\Internal\Enums\SaleState;
use Modules\Commerce\Internal\Enums\SessionState;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Models\Sale;

final class QuoteController
{
    public function index(Request $request): JsonResponse
    {
        $query = Sale::query()
            ->where('state', SaleState::Quote)
            ->with(['customer', 'cashSession.openedBy'])
            ->withCount('lines')
            ->latest('created_at');

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
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
            'client_id'       => ['sometimes', 'nullable', 'uuid', 'exists:customers,id'],
            'valid_until'     => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        if (isset($validated['idempotency_key'])) {
            $existing = Sale::query()->where('idempotency_key', $validated['idempotency_key'])->first();
            if ($existing !== null) {
                return response()->json(['data' => $existing->load('customer')->toArray()]);
            }
        }

        $quote = Sale::create([
            'cash_session_id' => null,
            'client_id'       => $validated['client_id'] ?? null,
            'valid_until'     => $validated['valid_until'] ?? null,
            'idempotency_key' => $validated['idempotency_key'] ?? (string) Str::uuid(),
            'state'           => SaleState::Quote,
        ]);

        return response()->json(['data' => $quote->load('customer')->toArray()], 201);
    }

    public function convert(Request $request, Sale $sale): JsonResponse
    {
        if ($sale->state !== SaleState::Quote) {
            return response()->json([
                'code'    => 'NOT_A_QUOTE',
                'message' => 'Seul un devis peut être converti en vente.',
                'champ'   => null,
            ], 409);
        }

        $validated = $request->validate([
            'cash_session_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        // Si cash_session_id non fourni, chercher la session active de l'utilisateur courant
        $sessionId = $validated['cash_session_id'] ?? null;

        if ($sessionId === null) {
            /** @var \App\Platform\Identity\Models\User|null $user */
            $user = Auth::user();

            if ($user !== null) {
                $activeSession = CashSession::query()
                    ->where('opened_by', (string) $user->getAuthIdentifier())
                    ->where('state', SessionState::Open)
                    ->latest('opened_at')
                    ->first();

                $sessionId = $activeSession?->id;
            }
        }

        if ($sessionId === null) {
            return response()->json([
                'code'    => 'NO_ACTIVE_SESSION',
                'message' => 'Aucune session de caisse ouverte. Ouvrez une session avant de convertir un devis.',
                'champ'   => 'cash_session_id',
            ], 422);
        }

        $session = CashSession::query()->whereKey($sessionId)->first();
        if ($session === null || ! $session->isOpen()) {
            return response()->json([
                'code'    => 'SESSION_CLOSED',
                'message' => 'La session de caisse est fermée.',
                'champ'   => 'cash_session_id',
            ], 409);
        }

        $sale->update([
            'cash_session_id' => $sessionId,
            'state'           => SaleState::Draft,
        ]);

        return response()->json(['data' => $sale->fresh()?->load(['lines', 'customer'])->toArray() ?? []]);
    }
}

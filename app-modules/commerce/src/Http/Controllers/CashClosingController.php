<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Commerce\Internal\Models\CashSession;
use Modules\Commerce\Internal\Services\CashClosingService;

final class CashClosingController
{
    public function __construct(private readonly CashClosingService $service)
    {
    }

    public function store(Request $request, CashSession $cashSession): JsonResponse
    {
        $data = $request->validate(['declared_cash' => 'required|integer|min:0']);

        try {
            $closing = $this->service->close(
                $cashSession,
                $data['declared_cash'],
                (string) Auth::id(),
            );
        } catch (\DomainException $e) {
            return response()->json(['code' => 'SESSION_NOT_OPEN', 'message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $closing->load('cashSession')], 201);
    }

    public function show(CashSession $cashSession): JsonResponse
    {
        $closing = $cashSession->closing;

        if ($closing === null) {
            return response()->json(['code' => 'NO_CLOSING', 'message' => 'Cette session n\'a pas encore été clôturée.'], 404);
        }

        return response()->json(['data' => $closing]);
    }
}

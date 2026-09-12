<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Commerce\Http\Requests\DashboardRequest;
use Modules\Commerce\Internal\Services\DashboardService;

final class DashboardController
{
    public function __construct(private readonly DashboardService $service)
    {
    }

    public function index(DashboardRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var \App\Platform\Identity\Models\User $user */
        $user = Auth::user();

        $result = $this->service->consolidate(
            $user,
            Carbon::parse($data['from'])->startOfDay(),
            Carbon::parse($data['to'])->endOfDay(),
        );

        return response()->json([
            'data' => [
                'total_sale_count'    => $result->totalSaleCount,
                'total_excluding_tax' => $result->totalExcludingTax,
                'total_tax'           => $result->totalTax,
                'total_including_tax' => $result->totalIncludingTax,
                'is_provisional'      => $result->isProvisional,
                'by_pos'              => $result->byPos,
            ],
        ]);
    }
}

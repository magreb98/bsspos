<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Http\Requests\StoreTransferRequest;
use Modules\Commerce\Http\Resources\TransferResource;
use Modules\Commerce\Internal\Enums\TransferStatus;
use Modules\Commerce\Internal\Models\PointOfSale;
use Modules\Commerce\Internal\Models\Transfer;
use Modules\Commerce\Internal\Services\TransferService;

final class TransferController
{
    public function __construct(private readonly TransferService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Transfer::with('sourcePos', 'destinationPos')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json(['data' => TransferResource::collection($query->get())]);
    }

    public function store(StoreTransferRequest $request): JsonResponse
    {
        $data = $request->validated();

        PointOfSale::findOrFail($data['source_pos_id']);
        PointOfSale::findOrFail($data['destination_pos_id']);

        $transfer = Transfer::create([
            'source_pos_id'      => $data['source_pos_id'],
            'destination_pos_id' => $data['destination_pos_id'],
            'status'             => TransferStatus::Pending,
            'notes'              => $data['notes'] ?? null,
        ]);

        foreach ($data['lines'] as $line) {
            $transfer->lines()->create([
                'product_id' => $line['product_id'],
                'quantity'   => $line['quantity'],
            ]);
        }

        $freshTransfer = $transfer->fresh()?->load('lines.product', 'sourcePos', 'destinationPos');

        return response()->json(['data' => $freshTransfer !== null ? new TransferResource($freshTransfer) : null], 201);
    }

    public function show(Transfer $transfer): JsonResponse
    {
        return response()->json(['data' => new TransferResource($transfer->load('lines.product', 'sourcePos', 'destinationPos'))]);
    }

    public function dispatch(Transfer $transfer): JsonResponse
    {
        try {
            $this->service->dispatch($transfer);
        } catch (\DomainException $e) {
            return response()->json(['code' => 'INVALID_STATE', 'message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => new TransferResource($transfer->fresh() ?? $transfer)]);
    }

    public function receive(Transfer $transfer): JsonResponse
    {
        try {
            $this->service->receive($transfer);
        } catch (\DomainException $e) {
            return response()->json(['code' => 'INVALID_STATE', 'message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => new TransferResource($transfer->fresh() ?? $transfer)]);
    }
}

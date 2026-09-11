<?php

declare(strict_types=1);

namespace Modules\Commerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_pos_id'      => 'required|uuid',
            'destination_pos_id' => 'required|uuid',
            'notes'              => 'nullable|string|max:1000',
            'lines'              => 'required|array|min:1',
            'lines.*.product_id' => 'required|uuid',
            'lines.*.quantity'   => 'required|integer|min:1',
        ]);

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

        return response()->json(['data' => $transfer->fresh()?->load('lines.product', 'sourcePos', 'destinationPos')], 201);
    }

    public function show(Transfer $transfer): JsonResponse
    {
        return response()->json(['data' => $transfer->load('lines.product', 'sourcePos', 'destinationPos')]);
    }

    public function dispatch(Transfer $transfer): JsonResponse
    {
        try {
            $this->service->dispatch($transfer);
        } catch (\DomainException $e) {
            return response()->json(['code' => 'INVALID_STATE', 'message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $transfer->fresh()]);
    }

    public function receive(Transfer $transfer): JsonResponse
    {
        try {
            $this->service->receive($transfer);
        } catch (\DomainException $e) {
            return response()->json(['code' => 'INVALID_STATE', 'message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $transfer->fresh()]);
    }
}

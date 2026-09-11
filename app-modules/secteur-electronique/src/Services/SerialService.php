<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Services;

use Modules\Commerce\Contracts\ReturnHandlerContract;
use Modules\Commerce\Contracts\SaleLineValidationContract;
use Modules\Commerce\Contracts\StockAllocationContract;
use Modules\Commerce\Internal\Models\SaleLine;
use Modules\SecteurElectronique\Enums\SerialStatus;
use Modules\SecteurElectronique\Models\SerialUnit;

final class SerialService implements StockAllocationContract, SaleLineValidationContract, ReturnHandlerContract
{
    /**
     * Assign a serial unit to a sale line (INV-02: unit must be available).
     * $context must carry 'serial_unit' => SerialUnit.
     *
     * @param array<string, mixed> $context
     * @throws \DomainException if the unit is not available
     */
    public function allocate(SaleLine $line, array $context = []): void
    {
        /** @var SerialUnit $unit */
        $unit = $context['serial_unit'];
        $this->assign($unit, $line);
    }

    /**
     * Release the serial unit linked to this sale line back to Available.
     *
     * @throws \DomainException if the line has no active allocation
     */
    public function release(SaleLine $line): void
    {
        $unit = SerialUnit::where('sale_line_id', $line->id)->first();

        if ($unit === null) {
            throw new \DomainException("Sale line #{$line->id} has no allocated serial unit.");
        }

        $unit->update([
            'status'       => SerialStatus::Available,
            'sale_line_id' => null,
        ]);
    }

    /**
     * Validate that the targeted serial unit is Available before confirming the line.
     * $context must carry 'serial_unit' => SerialUnit.
     *
     * @param array<string, mixed> $context
     * @throws \DomainException if the unit is not available
     */
    public function validate(SaleLine $line, array $context = []): void
    {
        /** @var SerialUnit $unit */
        $unit = $context['serial_unit'];

        if ($unit->status !== SerialStatus::Available) {
            throw new \DomainException(
                "Serial unit '{$unit->serial_number}' is not available (status: {$unit->status->value})."
            );
        }
    }

    /**
     * Release the serial unit after the sale line is returned.
     *
     * @throws \DomainException if the line has no allocated serial unit
     */
    public function onLineReturned(SaleLine $line): void
    {
        $this->release($line);
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    /**
     * Directly assign a unit to a line (used by allocate() and tests).
     *
     * @throws \DomainException if the unit is not available
     */
    public function assign(SerialUnit $unit, SaleLine $line): void
    {
        if ($unit->status !== SerialStatus::Available) {
            throw new \DomainException(
                "Serial unit '{$unit->serial_number}' is not available (status: {$unit->status->value})."
            );
        }

        $unit->update([
            'status'       => SerialStatus::Sold,
            'sale_line_id' => $line->id,
        ]);

        $line->update([
            'allocations' => ['serial_number' => $unit->serial_number],
        ]);
    }

    /**
     * Directly release a unit (used by tests and SAV flows).
     *
     * @throws \DomainException if the unit is not currently sold
     */
    public function releaseUnit(SerialUnit $unit): void
    {
        if ($unit->status !== SerialStatus::Sold) {
            throw new \DomainException(
                "Serial unit '{$unit->serial_number}' cannot be released (status: {$unit->status->value})."
            );
        }

        $unit->update([
            'status'       => SerialStatus::Available,
            'sale_line_id' => null,
        ]);
    }

    /**
     * Register a new serial unit for a product (status: Available).
     *
     * @throws \DomainException if the (product_id, serial_number) combination already exists
     */
    public function register(\Modules\Commerce\Internal\Models\Product $product, string $serialNumber): SerialUnit
    {
        if (SerialUnit::where('product_id', $product->id)
                       ->where('serial_number', $serialNumber)
                       ->exists()) {
            throw new \DomainException("Serial number '{$serialNumber}' already exists for this product.");
        }

        return SerialUnit::create([
            'product_id'    => $product->id,
            'serial_number' => $serialNumber,
            'status'        => SerialStatus::Available,
        ]);
    }
}

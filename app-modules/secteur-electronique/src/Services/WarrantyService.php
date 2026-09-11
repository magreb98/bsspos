<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Services;

use Modules\Commerce\Internal\Models\SaleLine;
use Modules\SecteurElectronique\Enums\SerialStatus;
use Modules\SecteurElectronique\Models\SerialUnit;
use Modules\SecteurElectronique\Models\Warranty;

final class WarrantyService
{
    /**
     * Issue a warranty for a sold serial unit.
     *
     * @throws \DomainException if unit is not sold or a warranty already exists
     */
    public function issue(SerialUnit $unit, SaleLine $line, int $months): Warranty
    {
        if ($unit->status !== SerialStatus::Sold) {
            throw new \DomainException(
                "Cannot issue warranty: serial unit '{$unit->serial_number}' is not sold."
            );
        }

        if (Warranty::where('serial_unit_id', $unit->id)->exists()) {
            throw new \DomainException(
                "A warranty already exists for serial unit '{$unit->serial_number}'."
            );
        }

        $startsOn  = now()->toDateString();
        $expiresOn = now()->addMonths($months)->toDateString();

        return Warranty::create([
            'serial_unit_id'  => $unit->id,
            'sale_line_id'    => $line->id,
            'starts_on'       => $startsOn,
            'expires_on'      => $expiresOn,
            'duration_months' => $months,
        ]);
    }

    public function isActive(Warranty $warranty): bool
    {
        return now()->toDateString() <= $warranty->expires_on;
    }
}

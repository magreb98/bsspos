<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Services;

use Modules\SecteurElectronique\Enums\SerialStatus;
use Modules\SecteurElectronique\Enums\TicketStatus;
use Modules\SecteurElectronique\Models\SerialUnit;
use Modules\SecteurElectronique\Models\ServiceTicket;

final class ServiceTicketService
{
    public function open(SerialUnit $unit, string $description): ServiceTicket
    {
        $unit->update(['status' => SerialStatus::InService]);

        return ServiceTicket::create([
            'serial_unit_id' => $unit->id,
            'status'         => TicketStatus::Open,
            'description'    => $description,
            'opened_at'      => now(),
        ]);
    }

    /**
     * @throws \DomainException if the ticket is already closed
     */
    public function close(ServiceTicket $ticket, SerialUnit $unit): void
    {
        if ($ticket->status === TicketStatus::Closed) {
            throw new \DomainException(
                "Service ticket '{$ticket->id}' is already closed."
            );
        }

        $ticket->update([
            'status'    => TicketStatus::Closed,
            'closed_at' => now(),
        ]);

        $unit->update(['status' => SerialStatus::Available]);
    }
}

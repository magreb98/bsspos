<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SecteurElectronique\Enums\TicketStatus;

final class ServiceTicket extends Model
{
    use HasUuids;

    protected $fillable = [
        'serial_unit_id',
        'status',
        'description',
        'repair_cost',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'status'      => TicketStatus::class,
        'repair_cost' => 'integer',
        'opened_at'   => 'datetime',
        'closed_at'   => 'datetime',
    ];

    /** @return BelongsTo<SerialUnit, $this> */
    public function serialUnit(): BelongsTo
    {
        return $this->belongsTo(SerialUnit::class, 'serial_unit_id');
    }
}

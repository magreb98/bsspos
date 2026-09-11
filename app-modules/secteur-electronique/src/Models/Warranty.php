<?php

declare(strict_types=1);

namespace Modules\SecteurElectronique\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Internal\Models\SaleLine;

final class Warranty extends Model
{
    use HasUuids;

    protected $fillable = [
        'serial_unit_id',
        'sale_line_id',
        'starts_on',
        'expires_on',
        'duration_months',
    ];

    protected $casts = [
        'starts_on'       => 'string',
        'expires_on'      => 'string',
        'duration_months' => 'integer',
    ];

    /** @return BelongsTo<SerialUnit, $this> */
    public function serialUnit(): BelongsTo
    {
        return $this->belongsTo(SerialUnit::class, 'serial_unit_id');
    }

    /** @return BelongsTo<SaleLine, $this> */
    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class, 'sale_line_id');
    }
}

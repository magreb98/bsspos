<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Internal\Enums\TransferStatus;

final class Transfer extends Model
{
    use HasUuids;

    protected $fillable = [
        'source_pos_id',
        'destination_pos_id',
        'status',
        'dispatched_at',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'status'        => TransferStatus::class,
        'dispatched_at' => 'datetime',
        'received_at'   => 'datetime',
    ];

    /** @return BelongsTo<PointOfSale, $this> */
    public function sourcePos(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'source_pos_id');
    }

    /** @return BelongsTo<PointOfSale, $this> */
    public function destinationPos(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'destination_pos_id');
    }

    /** @return HasMany<TransferLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(TransferLine::class, 'transfer_id');
    }
}

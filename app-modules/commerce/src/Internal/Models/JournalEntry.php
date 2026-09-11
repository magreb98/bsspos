<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class JournalEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'idempotency_key',
        'reference',
        'source_type',
        'source_id',
        'journal_code',
        'account_number',
        'label',
        'debit',
        'credit',
        'entry_date',
        'emitted_at',
    ];

    protected $casts = [
        'debit'      => 'integer',
        'credit'     => 'integer',
        'entry_date' => 'string',
        'emitted_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}

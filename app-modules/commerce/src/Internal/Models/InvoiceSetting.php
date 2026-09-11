<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class InvoiceSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'niu',
        'rccm',
        'company_name',
        'address',
        'phone',
    ];
}

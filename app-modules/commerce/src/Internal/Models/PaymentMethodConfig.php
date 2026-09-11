<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class PaymentMethodConfig extends Model
{
    use HasUuids;

    protected $table = 'payment_methods';

    protected $fillable = ['key', 'label', 'auto_confirm', 'active'];

    protected $casts = [
        'auto_confirm' => 'boolean',
        'active'       => 'boolean',
    ];
}

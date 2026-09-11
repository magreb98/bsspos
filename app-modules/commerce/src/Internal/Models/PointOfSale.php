<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Models;

use App\Platform\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PointOfSale extends Model
{
    use HasUuids;

    protected $table = 'points_of_sale';

    protected $fillable = ['name', 'organizational_unit_id', 'active'];

    protected $casts = ['active' => 'boolean'];

    /** @return BelongsTo<OrganizationalUnit, $this> */
    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    /** @return HasMany<CashRegister, $this> */
    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class, 'point_of_sale_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'member_point_of_sale', 'point_of_sale_id', 'member_id');
    }
}

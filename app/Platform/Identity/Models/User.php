<?php

declare(strict_types=1);

namespace App\Platform\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable implements AuditableContract
{
    use AuditableTrait;
    use HasUuids;
    use Notifiable;
    use HasRoles;

    protected $table = 'members';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $hidden = ['password'];

    protected string $guard_name = 'web';

    /** @var list<string> */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'password',
        'active',
        'last_connected_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'active' => 'boolean',
        'last_connected_at' => 'datetime',
    ];

    /** @return BelongsToMany<Perimeter, $this> */
    public function perimeters(): BelongsToMany
    {
        return $this->belongsToMany(Perimeter::class, 'member_perimeter', 'member_id', 'perimeter_id');
    }

    /**
     * Returns UUIDs of all visible perimeters (assigned node + subtree).
     * Uses WITH RECURSIVE — compatible with PostgreSQL 9.1+ and SQLite 3.35+.
     *
     * @return Collection<int, string>
     */
    public function visiblePerimeters(): Collection
    {
        $rootIds = $this->perimeters()->pluck('perimeters.id');

        if ($rootIds->isEmpty()) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, $rootIds->count(), '?'));

        $rows = DB::select(
            "WITH RECURSIVE subtree(id) AS (
                SELECT id FROM perimeters WHERE id IN ({$placeholders})
                UNION ALL
                SELECT p.id FROM perimeters p
                INNER JOIN subtree s ON p.parent_id = s.id
            )
            SELECT id FROM subtree",
            $rootIds->values()->all()
        );

        return collect($rows)->pluck('id');
    }

    public function isActive(): bool
    {
        return (bool) $this->active;
    }
}

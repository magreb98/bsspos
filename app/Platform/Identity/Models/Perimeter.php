<?php

declare(strict_types=1);

namespace App\Platform\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class Perimeter extends Model
{
    use HasUuids;

    protected $table = 'perimeters';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'type',
        'parent_id',
    ];

    /** @return BelongsTo<Perimeter, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Perimeter::class, 'parent_id');
    }

    /** @return HasMany<Perimeter, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Perimeter::class, 'parent_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'member_perimeter', 'perimeter_id', 'member_id');
    }

    /**
     * Returns this node and all its descendants via WITH RECURSIVE.
     * Compatible with PostgreSQL and SQLite 3.35+.
     *
     * @return Collection<int, Perimeter>
     */
    public function subtree(): Collection
    {
        $rows = DB::select(
            "WITH RECURSIVE subtree(id) AS (
                SELECT id FROM perimeters WHERE id = ?
                UNION ALL
                SELECT p.id FROM perimeters p
                INNER JOIN subtree s ON p.parent_id = s.id
            )
            SELECT p.* FROM perimeters p
            INNER JOIN subtree s ON p.id = s.id",
            [$this->id]
        );

        return collect($rows)->map(fn ($row) => (new Perimeter())->forceFill((array) $row));
    }
}

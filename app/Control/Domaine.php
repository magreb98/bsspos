<?php

declare(strict_types=1);

namespace App\Control;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Models\Domain as StanclDomain;

/**
 * Landlord model representing a tenant's access domain.
 *
 * The physical table is `domains` (required by Stancl for its DomainTenantResolver).
 * The physical domain column is `domain`; the `domaine` accessor/mutator maps to it
 * for backward compatibility with existing test fixtures.
 *
 * Stancl uses `->where('domain', ...)` in its resolvers — the physical `domain`
 * column must remain unchanged.
 */
final class Domaine extends StanclDomain
{
    /**
     * Physical table is `domains` (required by Stancl resolver).
     *
     * @var string
     */
    protected $table = 'domains';

    /** The `domains` table has no `updated_at` column. */
    public const UPDATED_AT = null;

    /**
     * Relation to the tenant that owns this domain.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Accessor: reads `domain` from the physical column.
     */
    public function getDomaineAttribute(): string
    {
        return (string) ($this->attributes['domain'] ?? '');
    }

    /**
     * Mutator: writes to the physical `domain` column.
     */
    public function setDomaineAttribute(string $value): void
    {
        $this->attributes['domain'] = $value;
    }
}

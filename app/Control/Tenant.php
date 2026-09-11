<?php

declare(strict_types=1);

namespace App\Control;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as StanclTenant;

/**
 * Landlord model representing a platform client (tenant).
 *
 * Extends the Stancl model to inherit traits:
 *   CentralConnection, GeneratesIds, HasDataColumn, HasInternalKeys, TenantRun.
 *
 * getCustomColumns() declares `name` and `status` as real columns so that
 * VirtualColumn does not serialize them into `data`.
 */
final class Tenant extends StanclTenant implements \Stancl\Tenancy\Contracts\TenantWithDatabase
{
    use HasDatabase;

    /** Primary key is a UUID string. */
    protected $keyType = 'string';

    /** No auto-increment for UUIDs. */
    public $incrementing = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'provisioning_step' => 0,
        'provisioning_error' => null,
    ];

    /** @var array<string, string> */
    protected $casts = [
        'provisioning_step' => 'integer',
    ];

    /**
     * Expands keys from the `data` column into virtual attributes before encoding.
     *
     * Stancl VirtualColumn only handles first-level attributes.
     * If the caller assigns `$tenant->data = ['key' => value]` directly,
     * the keys would be lost during encoding. This hook promotes them to
     * first-level attributes so they are correctly serialized by VirtualColumn.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $tenant): void {
            // Retrieve the decoded value of `data` (the array cast applies here).
            /** @var array<string, mixed>|null $data */
            $data = $tenant->getAttribute('data');

            if (! is_array($data) || empty($data)) {
                return;
            }

            // Promote each key of the `data` array to a first-level attribute
            // so that VirtualColumn serializes them correctly during encoding.
            foreach ($data as $key => $value) {
                $tenant->setAttribute((string) $key, $value);
            }

            $tenant->setAttribute('data', null);
        });
    }

    /**
     * Real columns of the `tenants` table.
     *
     * VirtualColumn stores in `data` any attribute absent from this list.
     * We add our metadata columns here so they remain as proper columns.
     *
     * @return string[]
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'status',
            'provisioning_step',
            'provisioning_error',
        ];
    }

    /**
     * Relation to domains associated with this tenant.
     *
     * Used by Stancl DomainTenantResolver (whereHas('domains')).
     *
     * @return HasMany<\App\Control\Domaine, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domaine::class, 'tenant_id');
    }

    /**
     * Alias relation to domains associated with this tenant.
     *
     * @return HasMany<\App\Control\Domaine, $this>
     */
    public function domaines(): HasMany
    {
        return $this->hasMany(Domaine::class, 'tenant_id');
    }

    /**
     * Indicates whether this tenant is active.
     */
    public function estActif(): bool
    {
        return $this->status === 'actif';
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tenant representation used across TenantController's index()/store()/
 * show()/update()/reprovision().
 *
 * Tenant (App\Control\Tenant) extends Stancl's tenant model, which uses
 * VirtualColumn to promote arbitrary keys from its `data` JSON column into
 * first-level attributes (e.g. `initial_admin` set by TenantController::
 * store()). Because that attribute set is dynamic and not limited to a
 * fixed list of columns, this resource delegates to the model's own
 * toArray() to preserve the exact current output rather than risking
 * silently dropping a dynamic field behind a hardcoded allowlist. The
 * `domains` relation (when loaded) is included exactly as it was before —
 * an array of raw Domaine attributes — matching prior behaviour.
 */
final class TenantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use App\Platform\Identity\Models\Perimeter;
use Modules\Commerce\Internal\Models\OrganizationalUnit;

final class PerimeterLinker
{
    /**
     * Creates a Perimeter node for the given OrganizationalUnit and links them.
     * Must be called within a tenant context (perimeters table lives in tenant DB).
     */
    public function link(OrganizationalUnit $unit): void
    {
        $parentPerimeterId = null;

        if ($unit->parent_id !== null) {
            $parentPerimeterId = OrganizationalUnit::where('id', $unit->parent_id)
                ->value('perimeter_id');
        }

        $type = $parentPerimeterId === null ? 'root' : 'store';

        $perimeter = Perimeter::create([
            'name'      => $unit->name,
            'type'      => $type,
            'parent_id' => $parentPerimeterId,
        ]);

        $unit->updateQuietly(['perimeter_id' => $perimeter->id]);
    }
}

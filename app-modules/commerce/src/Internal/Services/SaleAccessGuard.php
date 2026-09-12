<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Modules\Commerce\Internal\Models\Sale;

/**
 * Enforces the same row-level rule SaleController::index() already applies
 * when listing sales, on every single-resource Sale/Payment endpoint too:
 * a user without `inventory.write` (a plain vendeur) may only act on a sale
 * tied to a cash session they personally opened. Users with `inventory.write`
 * (gérant/propriétaire) can access any sale in the tenant.
 *
 * Route-model binding on {sale} only proves the sale exists, not that the
 * caller is allowed to touch it — this guard is the single place that check
 * lives, so it can't drift between endpoints.
 */
final class SaleAccessGuard
{
    public function ensureAccessible(Sale $sale): void
    {
        $user = Auth::user();

        if ($user === null || $user->can('inventory.write')) {
            return;
        }

        $sale->loadMissing('cashSession');

        if ($sale->cashSession?->opened_by !== (string) $user->getAuthIdentifier()) {
            throw new AuthorizationException("Vous n'avez pas accès à cette vente.");
        }
    }
}

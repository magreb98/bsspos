<?php

declare(strict_types=1);

namespace Modules\Commerce\Contracts;

use Modules\Commerce\Internal\Models\Sale;

/**
 * Variation point 3/5 — side effects triggered once a sale reaches Confirmed state.
 *
 * For serial sectors:  auto-issue a warranty for each serialized line.
 * For batch sectors:   record batch consumption audit entry.
 * For quantity sectors: no post-confirmation hook needed.
 */
interface PostConfirmationContract
{
    /**
     * React to a sale being confirmed.
     * Called once, after the sale state transitions to Confirmed.
     *
     * @throws \DomainException if the post-confirmation action cannot complete
     */
    public function onSaleConfirmed(Sale $sale): void;
}

<?php

declare(strict_types=1);

namespace App\Platform\Mcp;

use App\Platform\Identity\Models\User;

interface MetricAuthorizerContract
{
    /**
     * Authorizes access to a metric for the given user.
     *
     * @throws \DomainException if metric is unknown or user lacks permission
     */
    public function authorize(string $metricName, User $user): void;
}

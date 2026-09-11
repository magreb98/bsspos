<?php

declare(strict_types=1);

namespace Modules\Commerce\Internal\Metrics;

use App\Platform\Identity\Models\User;
use App\Platform\Mcp\MetricAuthorizerContract;

final class MetricAuthorizer implements MetricAuthorizerContract
{
    public function __construct(private readonly MetricGuard $guard)
    {
    }

    public function authorize(string $metricName, User $user): void
    {
        $this->guard->authorize($metricName, $user);
    }
}

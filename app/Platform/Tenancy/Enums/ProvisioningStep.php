<?php

declare(strict_types=1);

namespace App\Platform\Tenancy\Enums;

enum ProvisioningStep: int
{
    case NotStarted = 0;
    case DatabaseCreated = 1;
    case MigrationsApplied = 2;
    case ReferenceDataLoaded = 3;
    case AdminCreated = 4;
    case SubscriptionOpened = 5;
}

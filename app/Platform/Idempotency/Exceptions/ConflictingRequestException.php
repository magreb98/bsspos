<?php

declare(strict_types=1);

namespace App\Platform\Idempotency\Exceptions;

use RuntimeException;

final class ConflictingRequestException extends RuntimeException
{
}

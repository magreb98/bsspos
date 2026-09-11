<?php

declare(strict_types=1);

namespace App\Platform\Idempotency\Values;

final readonly class IdempotenceResult
{
    /**
     * @param array<string, mixed>|null $responseData
     */
    public function __construct(
        private bool $fresh,
        private ?array $responseData,
        private ?int $statusCode,
    ) {
    }

    public function isNew(): bool
    {
        return $this->fresh;
    }

    /** @return array<string, mixed>|null */
    public function response(): ?array
    {
        return $this->responseData;
    }

    public function status(): ?int
    {
        return $this->statusCode;
    }
}

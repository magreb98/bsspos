<?php

declare(strict_types=1);

namespace App\Platform\Mcp;

use App\Platform\Identity\Models\User;

interface McpToolContract
{
    public function name(): string;

    public function description(): string;

    /** @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function call(User $user, array $params): array;
}

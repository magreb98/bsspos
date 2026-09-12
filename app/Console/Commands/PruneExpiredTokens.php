<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Control\AdminToken;
use App\Control\Tenant;
use App\Platform\Identity\Models\MemberToken;
use App\Platform\Mcp\Models\McpToken;
use Illuminate\Console\Command;

class PruneExpiredTokens extends Command
{
    protected $signature = 'tokens:prune-expired';

    protected $description = 'Deletes expired admin, member and MCP bearer tokens';

    public function handle(): int
    {
        $adminDeleted = AdminToken::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        $this->line("Admin tokens pruned: {$adminDeleted}");

        $memberDeleted = 0;
        $mcpDeleted = 0;

        Tenant::query()->where('status', 'actif')->each(function (Tenant $tenant) use (&$memberDeleted, &$mcpDeleted): void {
            $tenant->run(function () use (&$memberDeleted, &$mcpDeleted): void {
                $memberDeleted += MemberToken::query()
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', now())
                    ->delete();

                $mcpDeleted += McpToken::query()
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', now())
                    ->delete();
            });
        });

        $this->line("Member tokens pruned: {$memberDeleted}");
        $this->line("MCP tokens pruned: {$mcpDeleted}");

        return self::SUCCESS;
    }
}

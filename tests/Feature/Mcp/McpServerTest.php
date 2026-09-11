<?php

declare(strict_types=1);

/**
 * Feature tests for the MCP server — Task 9.4
 *
 * Decisions:
 *   M1 — unknown tool returns 404 (not 403)
 *   M2 — tool call with valid token and metric permission returns result
 *   M3 — tool call is audited with both user_id and agent_id
 *   M4 — user without metric permission gets DomainException (403 response)
 */

use App\Control\Domaine;
use App\Control\Tenant;
use App\Platform\Identity\Models\User;
use App\Platform\Mcp\Models\McpAuditLog;
use App\Platform\Mcp\Models\McpToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Internal\Metrics\MetricCatalog;
use Modules\Commerce\Internal\Metrics\MetricDefinition;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function makeMcpTenant(string $name, string $domain): Tenant
{
    $tenant = Tenant::create(['name' => $name, 'status' => 'actif']);
    Domaine::create(['tenant_id' => $tenant->id, 'domaine' => $domain]);

    return $tenant;
}

function makeMcpUser(string $phone): User
{
    return User::create([
        'first_name' => 'Mcp',
        'last_name' => 'User',
        'phone' => $phone,
        'password' => bcrypt('secret'),
    ]);
}

function makeMcpToken(User $user, string $rawToken, string $name = 'TestAgent'): McpToken
{
    return McpToken::create([
        'user_id' => $user->id,
        'name' => $name,
        'token' => hash('sha256', $rawToken),
        'abilities' => ['*'],
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// M1 — unknown tool returns 404 (not 403)
// ─────────────────────────────────────────────────────────────────────────────

it('unknown_tool_returns_404', function (): void {
    $tenant = makeMcpTenant('Mcp Corp M1', 'mcp-m1.bsspos.cm');
    tenancy()->initialize($tenant);

    $user = makeMcpUser('+237600009901');
    $rawToken = 'raw-token-m1-' . uniqid();
    makeMcpToken($user, $rawToken);

    tenancy()->end();

    $response = $this->withHeaders([
        'Host' => 'mcp-m1.bsspos.cm',
        'Authorization' => 'Bearer ' . $rawToken,
    ])->postJson('/mcp/tools/call', [
        'tool' => 'nonexistent_tool',
        'params' => [],
    ]);

    $response->assertStatus(404);
});

// ─────────────────────────────────────────────────────────────────────────────
// M2 — tool call with valid token and metric permission returns result
// ─────────────────────────────────────────────────────────────────────────────

it('tool_call_with_valid_token_and_permission_returns_result', function (): void {
    $tenant = makeMcpTenant('Mcp Corp M2', 'mcp-m2.bsspos.cm');
    tenancy()->initialize($tenant);

    $permission = Permission::findOrCreate('commerce.rapport.lire', 'web');
    $user = makeMcpUser('+237600009902');
    $user->givePermissionTo($permission);

    $rawToken = 'raw-token-m2-' . uniqid();
    makeMcpToken($user, $rawToken);

    // Register the metric in the catalog
    $catalog = app(MetricCatalog::class);
    $catalog->register(new MetricDefinition('ca_ttc', 'commerce.rapport.lire', "Chiffre d'affaires TTC"));

    tenancy()->end();

    $response = $this->withHeaders([
        'Host' => 'mcp-m2.bsspos.cm',
        'Authorization' => 'Bearer ' . $rawToken,
    ])->postJson('/mcp/tools/call', [
        'tool' => 'query_metrics',
        'params' => [
            'metric' => 'ca_ttc',
            'from' => '2026-01-01',
            'to' => '2026-12-31',
        ],
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['metric', 'from', 'to', 'total_including_tax', 'sale_count']);
    $response->assertJsonFragment(['metric' => 'ca_ttc']);
});

// ─────────────────────────────────────────────────────────────────────────────
// M3 — tool call is audited with user_id and agent_id
// ─────────────────────────────────────────────────────────────────────────────

it('tool_call_is_audited_with_user_id_and_agent_name', function (): void {
    $tenant = makeMcpTenant('Mcp Corp M3', 'mcp-m3.bsspos.cm');
    tenancy()->initialize($tenant);

    $permission = Permission::findOrCreate('commerce.rapport.lire', 'web');
    $user = makeMcpUser('+237600009903');
    $user->givePermissionTo($permission);

    $rawToken = 'raw-token-m3-' . uniqid();
    makeMcpToken($user, $rawToken, 'AuditAgent');

    $catalog = app(MetricCatalog::class);
    $catalog->register(new MetricDefinition('ca_ttc', 'commerce.rapport.lire', "Chiffre d'affaires TTC"));

    tenancy()->end();

    $response = $this->withHeaders([
        'Host' => 'mcp-m3.bsspos.cm',
        'Authorization' => 'Bearer ' . $rawToken,
        'X-Mcp-Agent' => 'AuditAgent',
    ])->postJson('/mcp/tools/call', [
        'tool' => 'query_metrics',
        'params' => [
            'metric' => 'ca_ttc',
            'from' => '2026-01-01',
            'to' => '2026-12-31',
        ],
    ]);

    $response->assertStatus(200);

    tenancy()->initialize($tenant);

    $log = McpAuditLog::latest('called_at')->first();

    expect($log)->not->toBeNull();
    expect($log?->user_id)->toBe($user->id);
    expect($log?->agent_name)->toBe('AuditAgent');
    expect($log?->tool)->toBe('query_metrics');

    tenancy()->end();
});

// ─────────────────────────────────────────────────────────────────────────────
// M4 — user without metric permission gets 403 response
// ─────────────────────────────────────────────────────────────────────────────

it('user_without_metric_permission_gets_403', function (): void {
    $tenant = makeMcpTenant('Mcp Corp M4', 'mcp-m4.bsspos.cm');
    tenancy()->initialize($tenant);

    Permission::findOrCreate('commerce.rapport.lire', 'web');
    $user = makeMcpUser('+237600009904');
    // user has NO permission

    $rawToken = 'raw-token-m4-' . uniqid();
    makeMcpToken($user, $rawToken);

    $catalog = app(MetricCatalog::class);
    $catalog->register(new MetricDefinition('ca_ttc', 'commerce.rapport.lire', "Chiffre d'affaires TTC"));

    tenancy()->end();

    $response = $this->withHeaders([
        'Host' => 'mcp-m4.bsspos.cm',
        'Authorization' => 'Bearer ' . $rawToken,
    ])->postJson('/mcp/tools/call', [
        'tool' => 'query_metrics',
        'params' => [
            'metric' => 'ca_ttc',
            'from' => '2026-01-01',
            'to' => '2026-12-31',
        ],
    ]);

    $response->assertStatus(403);
});

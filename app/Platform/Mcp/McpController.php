<?php

declare(strict_types=1);

namespace App\Platform\Mcp;

use App\Platform\Identity\Models\User;
use App\Platform\Mcp\Models\McpAuditLog;
use App\Platform\Mcp\Tools\QueryMetricsTool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class McpController
{
    public function __construct(
        private readonly MetricListingContract $metricListing,
        private readonly MetricAuthorizerContract $metricAuthorizer,
    ) {
    }

    public function tools(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('mcp_user');

        $visibleDescriptors = $this->metricListing->visibleDescriptorsFor($user);

        $tool = new QueryMetricsTool($this->metricAuthorizer);

        $metricTools = array_map(fn ($desc) => [
            'name' => "query_metrics:{$desc['name']}",
            'description' => $desc['label'],
        ], $visibleDescriptors);

        $tools = array_merge($metricTools, [
            [
                'name' => $tool->name(),
                'description' => $tool->description(),
            ],
        ]);

        return response()->json(['tools' => $tools]);
    }

    public function call(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('mcp_user');

        /** @var string|null $agentName */
        $agentName = $request->attributes->get('mcp_agent');

        $toolName = (string) $request->input('tool', '');

        $tool = new QueryMetricsTool($this->metricAuthorizer);

        if ($toolName !== $tool->name()) {
            abort(404);
        }

        /** @var array<string, mixed> $params */
        $params = (array) $request->input('params', []);

        try {
            $result = $tool->call($user, $params);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        McpAuditLog::create([
            'user_id' => $user->id,
            'agent_name' => $agentName,
            'tool' => $toolName,
            'params' => $params,
            'called_at' => now(),
        ]);

        return response()->json($result);
    }
}

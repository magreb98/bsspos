<?php

declare(strict_types=1);

namespace App\Platform\Mcp\Middleware;

use App\Platform\Identity\Models\User;
use App\Platform\Mcp\Models\McpToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateMcpRequest
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (! is_string($authHeader) || ! str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $rawToken = substr($authHeader, 7);
        $hashed = hash('sha256', $rawToken);

        $mcpToken = McpToken::where('token', $hashed)->first();

        if ($mcpToken === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = User::find($mcpToken->user_id);

        if ($user === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $mcpToken->update(['last_used_at' => now()]);

        $agentName = $request->header('X-Mcp-Agent');

        $request->attributes->set('mcp_user', $user);
        $request->attributes->set('mcp_agent', is_string($agentName) ? $agentName : null);

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Control\AdminToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateAdminRequest
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (! is_string($authHeader) || ! str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['code' => 'UNAUTHENTICATED', 'message' => 'Non authentifié.'], 401);
        }

        $rawToken = substr($authHeader, 7);
        $hashed   = hash('sha256', $rawToken);

        $adminToken = AdminToken::with('adminUser')->where('token', $hashed)->first();

        if ($adminToken === null || $adminToken->adminUser === null) {
            return response()->json(['code' => 'UNAUTHENTICATED', 'message' => 'Token invalide.'], 401);
        }

        if (! $adminToken->adminUser->isActive()) {
            return response()->json(['code' => 'ACCOUNT_INACTIVE', 'message' => 'Ce compte est désactivé.'], 403);
        }

        $adminToken->update(['last_used_at' => now()]);

        $request->attributes->set('admin_user', $adminToken->adminUser);
        $request->attributes->set('admin_token', $adminToken);

        return $next($request);
    }
}

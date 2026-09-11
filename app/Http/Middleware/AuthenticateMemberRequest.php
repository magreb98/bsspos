<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Platform\Identity\Models\MemberToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateMemberRequest
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

        $memberToken = MemberToken::with('member')->where('token', $hashed)->first();

        if ($memberToken === null || $memberToken->member === null) {
            return response()->json(['code' => 'UNAUTHENTICATED', 'message' => 'Token invalide.'], 401);
        }

        if (! $memberToken->member->isActive()) {
            return response()->json(['code' => 'ACCOUNT_INACTIVE', 'message' => 'Ce compte est désactivé.'], 403);
        }

        $memberToken->update(['last_used_at' => now()]);
        $memberToken->member->withoutAuditing(
            fn () => $memberToken->member->update(['last_connected_at' => now()])
        );

        Auth::setUser($memberToken->member);

        return $next($request);
    }
}

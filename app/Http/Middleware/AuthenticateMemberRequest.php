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

        if ($memberToken->isExpired()) {
            $memberToken->delete();

            return response()->json(['code' => 'TOKEN_EXPIRED', 'message' => 'Token expiré.'], 401);
        }

        if (! $memberToken->member->isActive()) {
            return response()->json(['code' => 'ACCOUNT_INACTIVE', 'message' => 'Ce compte est désactivé.'], 403);
        }

        // A member whose password was never rotated off its initial
        // (phone-number-derived) value must change it before doing anything
        // else — except calling the change-password endpoint itself (or the
        // account could never get unstuck) and 'me'/'logout', which the
        // frontend needs to be able to reach unconditionally: 'me' is how it
        // discovers must_change_password in the first place (e.g. on a page
        // refresh, well after login already happened), and 'logout' must
        // always be reachable so a stuck member can at least sign out.
        $exemptRoutes = ['commerce.auth.change-password', 'commerce.auth.me', 'commerce.auth.logout'];

        if ($memberToken->member->must_change_password && ! $request->routeIs($exemptRoutes)) {
            return response()->json([
                'code'    => 'MUST_CHANGE_PASSWORD',
                'message' => 'Vous devez changer votre mot de passe avant de continuer.',
            ], 403);
        }

        $memberToken->update(['last_used_at' => now()]);
        $memberToken->member->withoutAuditing(
            fn () => $memberToken->member->update(['last_connected_at' => now()])
        );

        Auth::setUser($memberToken->member);

        return $next($request);
    }
}

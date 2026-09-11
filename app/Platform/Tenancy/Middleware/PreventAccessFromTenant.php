<?php

declare(strict_types=1);

namespace App\Platform\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Middleware de protection des routes landlord.
 *
 * Lève une réponse 403 si la requête est exécutée dans un contexte tenant initialisé.
 * Garantit l'étanchéité entre la base landlord et les bases tenant (Règle 2 CLAUDE.md).
 */
final class PreventAccessFromTenant
{
    /**
     * Lève une exception si la requête courante s'exécute dans un contexte tenant.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (tenancy()->initialized) {
            return response(__('errors.tenant_acces_interdit'), 403);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}

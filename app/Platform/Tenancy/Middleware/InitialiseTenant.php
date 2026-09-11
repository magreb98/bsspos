<?php

declare(strict_types=1);

namespace App\Platform\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware d'initialisation du tenant depuis le domaine HTTP.
 *
 * Délègue à `InitializeTenancyByDomain` de Stancl et renvoie une réponse 404
 * si le domaine est inconnu.
 */
final class InitialiseTenant
{
    public function __construct(
        private readonly InitializeTenancyByDomain $inner,
    ) {
    }

    /**
     * Initialise la session tenant depuis le domaine de la requête.
     * Renvoie 404 si le domaine n'est rattaché à aucun tenant enregistré.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        InitializeTenancyByDomain::$onFail = static function (
            TenantCouldNotBeIdentifiedException $e,
            Request $request,
            Closure $next,
        ): Response {
            return response('', 404);
        };

        return $this->inner->handle($request, $next);
    }
}

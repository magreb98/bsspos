<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateAdminRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Admin routes use Bearer token auth — no CSRF, no session needed.
            Route::middleware([
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
            ])->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Exclure les routes API tenant du CSRF (Bearer token auth)
        $middleware->validateCsrfTokens(except: [
            'commerce/*',
            'electronics/*',
            'mcp/*',
        ]);

        $middleware->alias([
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'authenticate.admin' => AuthenticateAdminRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->is('admin/*')
                || $request->is('commerce/*')
                || $request->is('electronics/*')
                || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                $firstField   = array_key_first($e->errors());
                $firstMessage = $e->errors()[$firstField][0] ?? 'Données invalides.';

                return response()->json([
                    'code'    => 'VALIDATION_ERROR',
                    'message' => $firstMessage,
                    'champ'   => $firstField,
                    // Full set of failures, so a client isn't limited to the first
                    // invalid field the way 'champ'/'message' above necessarily are.
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'code'    => 'FORBIDDEN',
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Action non autorisée.',
                    'champ'   => null,
                ], 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'code'    => 'NOT_FOUND',
                    'message' => 'Ressource introuvable.',
                    'champ'   => null,
                ], 404);
            }
        });
    })->create();

<?php

use App\Http\Middleware\BuyerIdentity;
use App\Http\Middleware\ResolveOrganization;
use App\Http\Middleware\ResolveWebOrganization;
use App\Http\Middleware\SetWebLocale;
use App\Http\Middleware\SupplierOrganization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetWebLocale::class]);
        $middleware->redirectGuestsTo(fn (Request $request) => route($request->is('portal*') ? 'portal.login' : 'login'));
        $middleware->redirectUsersTo(fn (Request $request) => route($request->is('portal*') ? 'portal.home' : 'dashboard'));
        $middleware->alias([
            'buyer.identity' => BuyerIdentity::class,
            'supplier.organization' => SupplierOrganization::class,
            'organization' => ResolveOrganization::class,
            'web.organization' => ResolveWebOrganization::class,
            'abilities' => CheckAbilities::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, SupplierOrganization::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveWebOrganization::class);
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            ResolveOrganization::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() === 409 && ! $request->is('api/*') && ! $request->expectsJson()
                && ! $request->isMethod('get') && $request->routeIs('tenders.*', 'items.*', 'invitations.*', 'portal.*')) {
                return back()->withErrors(['operation' => __($exception->getMessage())])->withInput($request->except('_token', 'password'));
            }

            return null;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

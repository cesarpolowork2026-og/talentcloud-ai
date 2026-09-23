<?php

use App\Tenancy\EstablecerContextoTenant;
use App\Tenancy\VerificarEmpresaActiva;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // En el grupo web (tras la sesión), así también cubren las peticiones de Livewire:
        // primero se expulsa a los usuarios de empresas suspendidas, luego se fija el tenant.
        $middleware->web(append: [
            VerificarEmpresaActiva::class,
            EstablecerContextoTenant::class,
        ]);

        // El contexto debe fijarse antes del route model binding; si no, los
        // modelos de la URL se resolverían sin empresa y darían 404.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: VerificarEmpresaActiva::class,
        );
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: EstablecerContextoTenant::class,
        );

        $middleware->alias([
            'rol' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

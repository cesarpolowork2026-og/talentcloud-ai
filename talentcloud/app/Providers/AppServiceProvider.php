<?php

namespace App\Providers;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Una instancia por petición o por Job: el contexto de empresa nunca se arrastra.
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Detecta consultas N+1 fuera de producción.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}

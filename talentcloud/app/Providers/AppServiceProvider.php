<?php

namespace App\Providers;

use App\Ia\HttpServicioIa;
use App\Ia\ServicioIa;
use App\Matching\FakeMatchingEngine;
use App\Matching\MatchingEngineInterface;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
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

        // Motor de matching según MATCHING_ENGINE. El adaptador HTTP del servicio de IA llega el día 15.
        $this->app->bind(MatchingEngineInterface::class, fn () => match (config('talentcloud.matching.motor')) {
            default => new FakeMatchingEngine,
        });

        $this->app->bind(ServicioIa::class, fn () => new HttpServicioIa(
            habilitado: (bool) config('talentcloud.ia.habilitada'),
            url: (string) config('talentcloud.ia.url'),
            apiKey: config('talentcloud.ia.api_key') ?: null,
            timeout: (int) config('talentcloud.ia.timeout'),
            reintentos: (int) config('talentcloud.ia.reintentos'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Detecta consultas N+1 fuera de producción.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Fechas en español ("23 sep", "30 de septiembre").
        Carbon::setLocale(config('app.locale'));
    }
}

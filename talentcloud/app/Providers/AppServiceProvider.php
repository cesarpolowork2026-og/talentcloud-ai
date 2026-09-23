<?php

namespace App\Providers;

use App\Enums\MotorMatching;
use App\Ia\HttpServicioIa;
use App\Ia\ServicioIa;
use App\Matching\FakeMatchingEngine;
use App\Matching\HttpMatchingEngine;
use App\Matching\MatchingEngineInterface;
use App\Matching\MotorConRespaldo;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Foundation\Application;
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

        // Motor de matching: con MATCHING_ENGINE=http y el servicio de IA habilitado, el del servicio con
        // respaldo en el análisis básico si falla; en cualquier otro caso, directamente el análisis básico.
        $this->app->bind(MatchingEngineInterface::class, function (Application $app) {
            $ia = $app->make(ServicioIa::class);

            return config('talentcloud.matching.motor') === MotorMatching::Http->value && $ia->habilitado()
                ? new MotorConRespaldo(new HttpMatchingEngine($ia), new FakeMatchingEngine)
                : new FakeMatchingEngine;
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

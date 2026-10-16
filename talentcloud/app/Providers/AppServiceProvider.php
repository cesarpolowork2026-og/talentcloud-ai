<?php

namespace App\Providers;

use App\Enums\MotorMatching;
use App\Ia\HttpServicioIa;
use App\Ia\ServicioIa;
use App\Matching\FakeMatchingEngine;
use App\Matching\HttpMatchingEngine;
use App\Matching\MatchingEngineInterface;
use App\Matching\MotorConRespaldo;
use App\Pagos\PasarelaDePago;
use App\Pagos\PasarelaStripe;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use LogicException;

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

        // Pasarela de pago (PAGOS_PASARELA). Solo se resuelve con el cobro en línea habilitado.
        $this->app->bind(PasarelaDePago::class, fn () => match (config('talentcloud.pagos.pasarela')) {
            'stripe' => PasarelaStripe::desdeConfig(),
            default => throw new LogicException('No hay pasarela de pago configurada (PAGOS_PASARELA).'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Contraseñas: 8+ caracteres con letras y números. En producción, además, que no figuren en
        // filtraciones conocidas (consulta anónima a Have I Been Pwned; en local y tests no hay red).
        Password::defaults(function (): Password {
            $regla = Password::min(8)->letters()->numbers();

            return $this->app->isProduction() ? $regla->uncompromised() : $regla;
        });

        // Detecta consultas N+1: fuera de producción lanzan una excepción; en producción no rompen la
        // página, pero quedan en el registro para encontrarlas.
        Model::preventLazyLoading();
        if ($this->app->isProduction()) {
            Model::handleLazyLoadingViolationUsing(function (Model $modelo, string $relacion): void {
                Log::warning('Carga perezosa (N+1) de la relación «'.$relacion.'» en '.$modelo::class);
            });
        }

        // Fechas en español ("23 sep", "30 de septiembre").
        Carbon::setLocale(config('app.locale'));

        $this->confiarEnLosProxies();
    }

    /**
     * Detrás del balanceador o proxy de GCP (HTTPS terminado antes de llegar aquí): sin esto,
     * Laravel creería que la petición es http y generaría enlaces y firmas http, y los límites
     * por IP verían la IP del proxy.
     */
    private function confiarEnLosProxies(): void
    {
        $proxies = (string) config('talentcloud.proxies');

        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    }
}

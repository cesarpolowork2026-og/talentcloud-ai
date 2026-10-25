<?php

namespace App\Providers;

use App\Models\Candidato;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Evaluacion;
use App\Models\Invitacion;
use App\Models\Postulacion;
use App\Models\PostulacionEtapa;
use App\Models\User;
use App\Models\Vacante;
use App\Pagos\PasarelaDePago;
use App\Pagos\PasarelaStripe;
use App\Support\CacheDeResumenes;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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

        // API pública: 60 peticiones por minuto y por IP.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        $this->confiarEnLosProxies();
        $this->invalidarResumenes();
    }

    /**
     * Las cifras del dashboard se guardan unos segundos (CacheDeResumenes). Toda escritura en lo que las alimenta
     * las deja viejas en el acto: una postulación, un cambio de etapa, una entrevista o un usuario nuevo se ven en
     * la siguiente carga. Escuchar los modelos (y no los eventos de dominio) cubre también lo que no emite evento.
     */
    private function invalidarResumenes(): void
    {
        $modelos = [Postulacion::class, PostulacionEtapa::class, Entrevista::class, Evaluacion::class, Vacante::class,
            User::class, Invitacion::class, Empresa::class, Candidato::class, Cv::class];

        // Tras confirmar la transacción: antes, otra petición podría recalcular con los datos viejos y guardarlos
        // bajo la versión nueva.
        $invalidar = function (Model $fila): void {
            $empresaId = $fila instanceof Empresa ? (int) $fila->getKey() : $fila->getAttribute('empresa_id');
            DB::afterCommit(fn () => CacheDeResumenes::invalidar($empresaId));
        };

        foreach ($modelos as $modelo) {
            $modelo::saved($invalidar);
            $modelo::deleted($invalidar);
        }
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

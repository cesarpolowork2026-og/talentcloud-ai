<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ProcesaEnLote;
use App\Enums\EstadoProcesamientoCv;
use App\Ia\ServicioIa;
use App\Jobs\AnalizarCvConIa;
use App\Jobs\GenerarEmbeddingsDeRequisitos;
use App\Models\Cv;
use App\Models\Vacante;
use App\Services\Cvs\AnalizarCv;
use App\Services\Vacantes\IndexarRequisitos;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Vuelve a analizar todos los CV y a generar los embeddings de todos los
 * requisitos. Se usa tras sembrar datos, al cambiar de modelo de embeddings o
 * si el servicio de IA estuvo caído.
 */
class ReindexarEmbeddings extends Command
{
    use ProcesaEnLote;

    protected $signature = 'talentcloud:reindexar-embeddings
        {--cvs : Solo los CV}
        {--requisitos : Solo los requisitos de las vacantes}
        {--sync : Ejecutar ahora, sin pasar por la cola (los errores se muestran al terminar)}';

    protected $description = 'Analiza los CV con el servicio de IA y genera los embeddings de los requisitos';

    public function handle(ServicioIa $ia, TenantContext $tenant, AnalizarCv $analizarCv, IndexarRequisitos $indexarRequisitos): int
    {
        if (! $ia->habilitado()) {
            $this->error('El servicio de IA está deshabilitado (IA_HABILITADA=false).');

            return self::FAILURE;
        }

        if (! $ia->disponible()) {
            $this->error('El servicio de IA no responde en '.config('talentcloud.ia.url').'. ¿Está en marcha?');

            return self::FAILURE;
        }

        $todo = ! $this->option('cvs') && ! $this->option('requisitos');
        $fallos = 0;

        if ($todo || $this->option('cvs')) {
            $cvs = Cv::where('estado_procesamiento', EstadoProcesamientoCv::Procesado)->orderBy('id')->pluck('id');
            // Un CV ya analizado no vuelve a avisar; uno que nunca se analizó (IA caída) sí, por primera vez.
            $fallos += $this->procesar('CV', $cvs, fn (int $id) => $this->option('sync')
                ? $analizarCv->ejecutar(Cv::findOrFail($id))
                : AnalizarCvConIa::dispatch($id));
        }

        if ($todo || $this->option('requisitos')) {
            $vacantes = $tenant->ejecutarGlobal(fn () => Vacante::has('requisitos')->orderBy('id')->get(['id', 'empresa_id']));
            $fallos += $this->procesar('vacantes', $vacantes, fn (Vacante $v) => $this->option('sync')
                ? $tenant->ejecutarPara($v->empresa_id, fn () => $indexarRequisitos->ejecutar($v))
                : GenerarEmbeddingsDeRequisitos::para($v));
        }

        return $fallos === 0 ? self::SUCCESS : self::FAILURE;
    }
}

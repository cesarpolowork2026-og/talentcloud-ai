<?php

namespace App\Services\Plataforma;

use App\DTO\MarcaDeResultadoIncorrecto;
use App\DTO\ResumenCalidadIa;
use App\Enums\EstadoProcesamientoCv;
use App\Enums\MotorMatching;
use App\Matching\RequisitoMatch;
use App\Models\Cv;
use App\Models\MatchFeedback;
use App\Models\Postulacion;
use App\Tenancy\EmpresaScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores de "Calidad de la IA" para el admin SaaS, de toda la plataforma
 * (lecturas sin filtro de empresa: solo las usa esta pantalla, que es del
 * admin). Nunca expone el contenido de un trabajo fallido ni datos del CV:
 * solo nombres de trabajos, cifras y lo que la IA dijo de cada requisito.
 */
class CalidadDeLaIa
{
    public const DIAS = 30;

    private const ULTIMOS_FALLIDOS = 10;

    private const ULTIMAS_MARCAS = 20;

    public function resumen(): ResumenCalidadIa
    {
        return $this->construir(conListas: true);
    }

    /**
     * Solo los contadores, sin las listas de marcas y trabajos fallidos: es lo
     * que muestra el Panel de la plataforma, que se recalcula en cada
     * interacción.
     */
    public function indicadores(): ResumenCalidadIa
    {
        return $this->construir(conListas: false);
    }

    private function construir(bool $conListas): ResumenCalidadIa
    {
        $desde = now()->subDays(self::DIAS);
        $conteos = $this->conteosDeMatching($desde);

        return new ResumenCalidadIa(
            // Un análisis básico por cupo de IA agotado (plan de la empresa) no es una falla del servicio.
            calculadas: $conteos['calculadas'] - $conteos['sin_cupo'],
            conIa: $conteos['con_ia'],
            basicas: $conteos['basicas'],
            basicasPendientes: $conteos['basicas_pendientes'],
            cvsConError: Cv::query()->where('estado_procesamiento', EstadoProcesamientoCv::Error)->count(),
            trabajosFallidos: DB::table('failed_jobs')->count(),
            ultimosTrabajosFallidos: $conListas ? $this->ultimosTrabajosFallidos() : collect(),
            marcasDelPeriodo: MatchFeedback::sinFiltroDeEmpresa()->where('created_at', '>=', $desde)->count(),
            marcas: $conListas ? $this->ultimasMarcas() : collect(),
            sinCupo: $conteos['sin_cupo'],
        );
    }

    /**
     * Los conteos de matching de toda la plataforma en una sola lectura de la
     * tabla (con cinco consultas separadas, el panel la recorría cinco veces).
     *
     * @return array{calculadas: int, sin_cupo: int, con_ia: int, basicas: int, basicas_pendientes: int}
     */
    private function conteosDeMatching(Carbon $desde): array
    {
        $delPeriodo = 'match_motor IS NOT NULL AND match_calculado_at >= ?';
        $basica = 'match_motor = ? AND NOT ia_sin_cupo';
        $fake = MotorMatching::Fake->value;

        $fila = Postulacion::sinFiltroDeEmpresa()->toBase()
            ->selectRaw("COUNT(*) FILTER (WHERE {$delPeriodo}) AS calculadas", [$desde])
            ->selectRaw("COUNT(*) FILTER (WHERE {$delPeriodo} AND ia_sin_cupo) AS sin_cupo", [$desde])
            ->selectRaw("COUNT(*) FILTER (WHERE {$delPeriodo} AND match_motor = ?) AS con_ia", [$desde, MotorMatching::Http->value])
            ->selectRaw("COUNT(*) FILTER (WHERE {$delPeriodo} AND {$basica}) AS basicas", [$desde, $fake])
            ->selectRaw("COUNT(*) FILTER (WHERE {$basica}) AS basicas_pendientes", [$fake])
            ->first();

        return array_map(fn ($n) => (int) $n, (array) $fila);
    }

    /**
     * Solo el nombre del trabajo, su cola y la fecha: la excepción y los datos
     * del trabajo pueden contener texto de un CV y no se leen.
     *
     * @return Collection<int, array{nombre: string, cola: string, fecha: Carbon}>
     */
    private function ultimosTrabajosFallidos(): Collection
    {
        return DB::table('failed_jobs')
            ->select(['queue', 'payload', 'failed_at'])
            ->latest('failed_at')
            ->limit(self::ULTIMOS_FALLIDOS)
            ->get()
            ->map(fn (object $fila) => [
                'nombre' => class_basename((string) (json_decode((string) $fila->payload, true)['displayName'] ?? 'Trabajo desconocido')),
                'cola' => (string) $fila->queue,
                'fecha' => Carbon::parse((string) $fila->failed_at),
            ]);
    }

    /** @return Collection<int, MarcaDeResultadoIncorrecto> */
    private function ultimasMarcas(): Collection
    {
        $sinFiltro = fn ($q) => $q->withoutGlobalScope(EmpresaScope::class);

        return MatchFeedback::sinFiltroDeEmpresa()
            ->with([
                'postulacion' => fn ($q) => $sinFiltro($q)
                    ->select(['id', 'empresa_id', 'vacante_id', 'match_detalle'])
                    ->with(['vacante' => fn ($q) => $sinFiltro($q)->select(['id', 'empresa_id', 'titulo'])->with('empresa:id,nombre')]),
                'requisito' => fn ($q) => $sinFiltro($q)->select(['id', 'competencia_id'])->with('competencia:id,nombre'),
                'usuario:id,name',
            ])
            ->latest()
            ->limit(self::ULTIMAS_MARCAS)
            ->get()
            ->map(function (MatchFeedback $marca) {
                $resultado = $this->resultadoDeLaIa($marca);

                return new MarcaDeResultadoIncorrecto(
                    empresa: (string) $marca->postulacion?->vacante?->empresa?->nombre,
                    vacante: (string) $marca->postulacion?->vacante?->titulo,
                    requisito: (string) $marca->requisito?->competencia?->nombre,
                    nivelDeLaIa: $resultado?->nivel,
                    evidencia: $resultado?->evidencia,
                    marcadaPor: $marca->usuario->name ?? 'Usuario eliminado',
                    fecha: $marca->created_at,
                    comentario: $marca->comentario,
                    urlMatching: route('matching.show', $marca->postulacion_id),
                );
            });
    }

    /** Lo que la IA dijo de ese requisito en el matching guardado de la postulación. */
    private function resultadoDeLaIa(MatchFeedback $marca): ?RequisitoMatch
    {
        foreach ((array) ($marca->postulacion->match_detalle['requisitos'] ?? []) as $requisito) {
            if (is_array($requisito) && (int) ($requisito['requisito_id'] ?? 0) === $marca->requisito_id) {
                return RequisitoMatch::desdeArreglo($requisito);
            }
        }

        return null;
    }
}

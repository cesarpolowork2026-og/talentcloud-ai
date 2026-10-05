<?php

namespace App\Services\Cvs;

use App\Enums\EstadoProcesamientoCv;
use App\Events\CvAnalizado;
use App\Ia\AnalisisCv;
use App\Ia\CompetenciaExtraida;
use App\Ia\ServicioIa;
use App\Models\Competencia;
use App\Models\Cv;
use App\Services\Candidatos\CompetenciasDelCandidato;
use Illuminate\Support\Facades\DB;

/**
 * Analiza con el servicio de IA un CV ya extraído y guarda el resultado:
 * datos estructurados, fragmentos con su embedding y las competencias
 * acreditadas del candidato (fuente = extraccion).
 */
class AnalizarCv
{
    public function __construct(
        private readonly ServicioIa $ia,
        private readonly CompetenciasDelCandidato $competencias,
    ) {}

    /**
     * false si el CV no tiene texto que analizar. Solo el primer análisis del CV
     * avisa y anota actividad (también si llega tarde, p. ej. por un reindexado
     * tras una caída de la IA); reanalizarlo no repite los avisos.
     */
    public function ejecutar(Cv $cv): bool
    {
        if ($cv->estado_procesamiento !== EstadoProcesamientoCv::Procesado || blank($cv->texto_extraido)) {
            return false;
        }

        $analisis = $this->ia->analizarCv($cv->id, $cv->texto_extraido, $this->catalogo());

        $primeraVez = DB::transaction(function () use ($cv, $analisis): bool {
            // Se decide con la fila bloqueada: si dos análisis del mismo CV coinciden
            // (la cola y un reindexado), solo el primero que guarda avisa.
            $primeraVez = Cv::whereKey($cv->id)->lockForUpdate()->value('datos_estructurados') === null;

            $this->guardarFragmentos($cv, $analisis);

            $cv->update(['datos_estructurados' => [...$analisis->datosEstructurados(), 'analizado_at' => now()->toIso8601String()]]);

            // Un CV anterior que termina de analizarse tarde no pisa las competencias del CV vigente.
            if ($this->esElCvActual($cv)) {
                $this->competencias->reemplazarExtraidas($cv->candidato_id, $this->delCatalogo($analisis->competenciasAcreditadas()));
            }

            return $primeraVez;
        });

        CvAnalizado::dispatch($cv, $primeraVez);

        return true;
    }

    /**
     * El catálogo es la fuente de verdad de las competencias: se envía completo en cada análisis.
     *
     * @return list<array{id: int, nombre: string, sinonimos: list<string>}>
     */
    private function catalogo(): array
    {
        return Competencia::query()
            ->orderBy('id')
            ->get(['id', 'nombre', 'sinonimos'])
            ->map(fn (Competencia $c) => ['id' => $c->id, 'nombre' => $c->nombre, 'sinonimos' => $c->sinonimos ?? []])
            ->values()
            ->all();
    }

    private function guardarFragmentos(Cv $cv, AnalisisCv $analisis): void
    {
        $cv->fragmentos()->delete();

        $cv->fragmentos()->createMany(array_map(fn ($fragmento) => [
            'seccion' => $fragmento->seccion,
            'orden' => $fragmento->orden,
            'texto' => $fragmento->texto,
            'embedding' => $fragmento->embedding,
            'embedding_modelo' => $analisis->modeloEmbeddings,
        ], $analisis->fragmentos));
    }

    private function esElCvActual(Cv $cv): bool
    {
        return Cv::where('candidato_id', $cv->candidato_id)->max('id') === $cv->id;
    }

    /**
     * Descarta competencias que dejaron de existir en el catálogo mientras se analizaba.
     *
     * @param  list<CompetenciaExtraida>  $extraidas
     * @return list<CompetenciaExtraida>
     */
    private function delCatalogo(array $extraidas): array
    {
        $existentes = Competencia::whereKey(array_map(fn (CompetenciaExtraida $c) => $c->competenciaId, $extraidas))
            ->pluck('id')
            ->all();

        return array_values(array_filter($extraidas, fn (CompetenciaExtraida $c) => in_array($c->competenciaId, $existentes, true)));
    }
}

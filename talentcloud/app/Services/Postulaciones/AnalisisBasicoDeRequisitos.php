<?php

namespace App\Services\Postulaciones;

use App\DTO\CoberturaDeRequisitos;
use App\Models\Competencia;
use App\Models\Requisito;
use App\Models\Vacante;
use App\Support\BuscadorDeTerminos;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Análisis básico de una postulación, sin IA: para cada requisito de la vacante busca la competencia (su nombre o un
 * sinónimo del catálogo) como palabra completa en el texto del CV, y la da por cubierta también si la empresa la anotó
 * en la ficha del candidato. Cada coincidencia trae el fragmento del CV como evidencia. La comparación semántica
 * (sinónimos que el catálogo no conoce, experiencia descrita con otras palabras) es de la unidad siguiente.
 */
class AnalisisBasicoDeRequisitos
{
    public function __construct(private readonly TenantContext $contexto) {}

    /**
     * Términos con los que se busca un requisito en un CV: el nombre de la competencia y sus sinónimos.
     *
     * @return list<string>
     */
    public function terminos(Requisito $requisito): array
    {
        $competencia = $requisito->competencia;

        return $competencia === null ? [] : array_values(array_filter([$competencia->nombre, ...$competencia->sinonimos]));
    }

    /**
     * Cobertura de varios candidatos a la vez (el tablero): dos consultas en total, sin importar cuántos sean.
     *
     * @param  list<int>  $candidatoIds
     * @return array<int, CoberturaDeRequisitos> por candidato
     */
    public function paraCandidatos(Vacante $vacante, array $candidatoIds): array
    {
        $requisitos = $vacante->requisitos()->with('competencia')->orderBy('orden')->get();
        $candidatoIds = array_values(array_unique($candidatoIds));

        if ($candidatoIds === []) {
            return [];
        }

        // El texto del CV con el que postuló cada persona; si la postulación no lo registró, el de su último CV. Es la
        // misma regla de Postulacion::cvEnviado(), en una sola consulta para todo el tablero.
        $textos = DB::table('postulaciones as p')
            ->leftJoin('cvs as enviado', 'enviado.id', '=', 'p.cv_id')
            ->leftJoinSub(
                DB::table('cvs')->selectRaw('DISTINCT ON (candidato_id) candidato_id, texto_extraido')->orderBy('candidato_id')->orderByDesc('id'),
                'ultimo',
                'ultimo.candidato_id',
                '=',
                'p.candidato_id',
            )
            ->where('p.vacante_id', $vacante->id)
            ->whereIn('p.candidato_id', $candidatoIds)
            ->selectRaw('p.candidato_id, CASE WHEN p.cv_id IS NOT NULL THEN enviado.texto_extraido ELSE ultimo.texto_extraido END AS texto')
            ->pluck('texto', 'candidato_id');

        $anotadas = $this->anotadas($candidatoIds);

        $resultado = [];
        foreach ($candidatoIds as $id) {
            $resultado[$id] = $this->evaluar($requisitos, (string) ($textos[$id] ?? ''), $anotadas[$id] ?? []);
        }

        return $resultado;
    }

    /**
     * Cobertura de un candidato con el texto de su CV ya cargado (la ficha de la postulación).
     *
     * @param  list<int>  $competenciasAnotadas  ids de competencias anotadas por la empresa
     */
    public function para(Vacante $vacante, ?string $textoDelCv, array $competenciasAnotadas = []): CoberturaDeRequisitos
    {
        return $this->evaluar($vacante->requisitos()->with('competencia')->orderBy('orden')->get(), (string) $textoDelCv, $competenciasAnotadas);
    }

    /**
     * Cobertura con requisitos ya cargados y solo el texto del CV (sin competencias anotadas, que son de cada empresa):
     * la que ve el postulante en las vacantes publicadas, donde no hay contexto de empresa.
     *
     * @param  Collection<int, Requisito>  $requisitos
     */
    public function paraRequisitos(Collection $requisitos, ?string $textoDelCv): CoberturaDeRequisitos
    {
        return $this->evaluar($requisitos, (string) $textoDelCv, []);
    }

    /**
     * @param  Collection<int, Requisito>  $requisitos
     * @param  list<int>  $anotadas
     */
    private function evaluar(Collection $requisitos, string $texto, array $anotadas): CoberturaDeRequisitos
    {
        $items = [];

        foreach ($requisitos as $requisito) {
            $zona = BuscadorDeTerminos::posiciones($texto, $this->terminos($requisito), soloLaPrimera: true)[0] ?? null;

            if ($zona !== null) {
                $items[] = ['requisito' => $requisito, 'cubierto' => true, 'via' => 'cv', 'fragmento' => BuscadorDeTerminos::fragmento($texto, $zona[0], $zona[1])];
            } elseif (in_array($requisito->competencia_id, $anotadas, true)) {
                $items[] = ['requisito' => $requisito, 'cubierto' => true, 'via' => 'anotada', 'fragmento' => null];
            } else {
                $items[] = ['requisito' => $requisito, 'cubierto' => false, 'via' => null, 'fragmento' => null];
            }
        }

        return new CoberturaDeRequisitos($items, conCv: trim($texto) !== '');
    }

    /**
     * Competencias que la empresa en curso anotó a cada candidato (el admin ve las de todas).
     *
     * @param  list<int>  $candidatoIds
     * @return array<int, list<int>>
     */
    public function anotadas(array $candidatoIds): array
    {
        if (! $this->contexto->esGlobal() && ! $this->contexto->tieneEmpresa()) {
            return [];
        }

        return DB::table('candidato_competencia')
            ->whereIn('candidato_id', $candidatoIds)
            ->when(! $this->contexto->esGlobal(), fn ($q) => $q->where('empresa_id', $this->contexto->id()))
            ->get(['candidato_id', 'competencia_id'])
            ->groupBy('candidato_id')
            ->map(fn (Collection $filas) => $filas->pluck('competencia_id')->map(fn ($id) => (int) $id)->all())
            ->all();
    }
}

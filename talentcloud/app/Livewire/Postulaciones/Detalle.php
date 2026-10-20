<?php

namespace App\Livewire\Postulaciones;

use App\Models\Cv;
use App\Models\Postulacion;
use App\Models\Vacante;
use App\Services\Postulaciones\AnalisisBasicoDeRequisitos;
use App\Support\BuscadorDeTerminos;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Una postulación vista de cerca: en qué etapa está, el CV, las entrevistas, las evaluaciones y las notas internas
 * del equipo. Es la ficha a la que llevan la selección, los candidatos y los avisos.
 */
class Detalle extends Component
{
    #[Locked]
    public int $postulacionId;

    public function mount(Postulacion $postulacion): void
    {
        $this->authorize('viewAny', Vacante::class);
        $this->authorize('view', $postulacion);

        $this->postulacionId = $postulacion->id;
    }

    public function render(AnalisisBasicoDeRequisitos $analisis): View
    {
        // Con el filtro por empresa, una postulación de otra empresa no se encuentra.
        $postulacion = Postulacion::query()
            ->with([
                'candidato.cvActual' => Cv::ligero(),
                'vacante.empresa',
                'vacante.requisitos.competencia',
                'entrevistas.entrevistador:id,name',
                'evaluaciones.evaluador:id,name',
            ])
            ->find($this->postulacionId);

        abort_if($postulacion === null, 404);

        // El texto completo del CV (la relación «ligera» solo trae un extracto) para el análisis y para leerlo en pantalla.
        $texto = $postulacion->candidato?->cvActual
            ? (string) Cv::query()->whereKey($postulacion->candidato->cvActual->id)->value('texto_extraido')
            : '';
        $cobertura = $analisis->para(
            $postulacion->vacante,
            $texto,
            $postulacion->candidato ? ($analisis->anotadas([$postulacion->candidato_id])[$postulacion->candidato_id] ?? []) : [],
        );
        $terminos = collect($cobertura->items)->flatMap(fn (array $i) => $analisis->terminos($i['requisito']))->all();

        return view('livewire.postulaciones.detalle', [
            'cobertura' => $cobertura,
            'textoDelCv' => $texto,
            'tramosDelCv' => BuscadorDeTerminos::tramos($texto, $terminos),
            'postulacion' => $postulacion,
            'entrevistas' => $postulacion->entrevistas->sortByDesc('inicia_at'),
            'evaluaciones' => $postulacion->evaluaciones->sortByDesc('created_at'),
            // Vista global (admin SaaS): los procesos del mismo candidato en otras vacantes, de cualquier empresa.
            'otrosProcesos' => app(TenantContext::class)->esGlobal()
                ? Postulacion::query()
                    ->where('candidato_id', $postulacion->candidato_id)
                    ->whereKeyNot($postulacion->id)
                    ->with('vacante.empresa:id,nombre')
                    ->latest()
                    ->get()
                : collect(),
        ])->title('Postulación · '.$postulacion->candidato?->nombre);
    }
}

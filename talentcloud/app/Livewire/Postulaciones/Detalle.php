<?php

namespace App\Livewire\Postulaciones;

use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Livewire\Concerns\PreguntaSiCancelarEntrevistas;
use App\Livewire\Concerns\SeActualizaSola;
use App\Models\Cv;
use App\Models\Entrevista;
use App\Models\Evaluacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Evaluaciones\GuardarEvaluacion;
use App\Services\Postulaciones\AnalisisBasicoDeRequisitos;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Support\BuscadorDeTerminos;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Una postulación vista de cerca: en qué etapa está, el CV, las entrevistas, las evaluaciones y las notas internas
 * del equipo. Es la ficha a la que llevan la selección, los candidatos y los avisos.
 */
class Detalle extends Component
{
    use PreguntaSiCancelarEntrevistas, SeActualizaSola;

    #[Locked]
    public int $postulacionId;

    /** «Mover a…» desde la ficha (las mismas reglas que el tablero: MoverPostulacion). */
    public string $etapaDestino = '';

    public bool $mostrarDescarte = false;

    public function mount(Postulacion $postulacion): void
    {
        $this->authorize('viewAny', Vacante::class);
        $this->authorize('view', $postulacion);

        $this->postulacionId = $postulacion->id;
    }

    public function moverA(MoverPostulacion $mover): void
    {
        $postulacion = $this->postulacion();
        $this->authorize('update', $postulacion);

        $this->validate(['etapaDestino' => ['required', Rule::enum(EtapaPostulacion::class)]], attributes: ['etapaDestino' => 'etapa']);
        $destino = EtapaPostulacion::from($this->etapaDestino);

        if ($destino === $postulacion->etapa) {
            return;
        }

        try {
            // Arriba de la columna destino, como «Mover a…» del tablero.
            if (! $this->moverOPreguntar($mover, $postulacion, $destino, 0, $this->usuario())) {
                return;
            }
        } catch (OperacionNoPermitida $e) {
            $this->addError('etapaDestino', $e->getMessage());

            return;
        }

        $this->etapaDestino = '';
        $this->dispatch('postulacion-movida', etapa: $destino->etiqueta());
    }

    /** Respuesta a «¿Cancelar la entrevista?» al devolver la postulación antes de «Entrevista». */
    public function confirmarRetroceso(bool $cancelar, MoverPostulacion $mover): void
    {
        $pendiente = $this->tomarRetroceso();

        if ($pendiente === null || $pendiente['postulacion'] !== $this->postulacionId) {
            return;
        }

        $postulacion = $this->postulacion();
        $this->authorize('update', $postulacion);

        try {
            $mover->ejecutar($postulacion, $pendiente['destino'], 0, $this->usuario(), $cancelar);
        } catch (OperacionNoPermitida $e) {
            $this->addError('etapaDestino', $e->getMessage());

            return;
        }

        $this->etapaDestino = '';
        $this->dispatch('postulacion-movida', etapa: $pendiente['destino']->etiqueta());
    }

    public function confirmarDescarte(): void
    {
        $this->authorize('update', $this->postulacion());

        $this->resetErrorBag();
        $this->mostrarDescarte = true;
    }

    public function descartar(MoverPostulacion $mover): void
    {
        $postulacion = $this->postulacion();
        $this->authorize('update', $postulacion);

        $this->mostrarDescarte = false;

        try {
            $mover->descartar($postulacion, $this->usuario());
        } catch (OperacionNoPermitida $e) {
            $this->addError('etapaDestino', $e->getMessage());

            return;
        }

        $this->dispatch('postulacion-movida', etapa: EtapaPostulacion::Descartado->etiqueta());
    }

    private function postulacion(): Postulacion
    {
        $postulacion = Postulacion::query()->with('vacante')->find($this->postulacionId);
        abort_if($postulacion === null, 404);

        return $postulacion;
    }

    private function usuario(): User
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        return $usuario;
    }

    /** «Evaluar» solo cuando se puede: entrevista realizada, no retirada y aún sin evaluar por esta persona. */
    private function puedeEvaluar(Postulacion $postulacion): bool
    {
        if (! $this->usuario()->can('create', Evaluacion::class)) {
            return false;
        }

        try {
            app(GuardarEvaluacion::class)->verificarEvaluable($postulacion, $this->usuario());

            return true;
        } catch (OperacionNoPermitida) {
            return false;
        }
    }

    /** La etapa (la mueve otra persona o la retira el postulante), sus entrevistas y evaluaciones. */
    protected function partesDeLaFirma(): array
    {
        return [
            $this->parteDeFila(Postulacion::query(), $this->postulacionId),
            $this->parteDe(Entrevista::query()->where('postulacion_id', $this->postulacionId)),
            $this->parteDe(Evaluacion::query()->where('postulacion_id', $this->postulacionId)),
        ];
    }

    public function render(AnalisisBasicoDeRequisitos $analisis): View
    {
        // Con el filtro por empresa, una postulación de otra empresa no se encuentra.
        $postulacion = Postulacion::query()
            ->with([
                'cv' => Cv::ligero(),
                'candidato.cvActual' => Cv::ligero(),
                'vacante.empresa',
                'vacante.requisitos.competencia',
                'entrevistas.entrevistador:id,name',
                'evaluaciones.evaluador:id,name',
            ])
            ->find($this->postulacionId);

        abort_if($postulacion === null, 404);

        // El CV con el que se postuló (no el último que subió el candidato). La relación «ligera» solo trae un extracto: el
        // texto completo se lee aparte, para el análisis y para leerlo en pantalla.
        $cv = $postulacion->cvEnviado();
        $texto = $cv ? (string) Cv::query()->whereKey($cv->id)->value('texto_extraido') : '';
        $cobertura = $analisis->para(
            $postulacion->vacante,
            $texto,
            $postulacion->candidato ? ($analisis->anotadas([$postulacion->candidato_id])[$postulacion->candidato_id] ?? []) : [],
        );
        $terminos = collect($cobertura->items)->flatMap(fn (array $i) => $analisis->terminos($i['requisito']))->all();

        return view('livewire.postulaciones.detalle', [
            'cv' => $cv,
            // Subió otro CV después de postular: se avisa, el de esta postulación sigue siendo el enviado.
            'cvMasReciente' => $cv !== null && $postulacion->candidato?->cvActual !== null && $postulacion->candidato->cvActual->id !== $cv->id
                ? $postulacion->candidato->cvActual
                : null,
            'cobertura' => $cobertura,
            // Acciones: el personal de la empresa, con la vacante abierta y la postulación no retirada (el admin SaaS solo supervisa).
            'editable' => $postulacion->vacante?->estado !== EstadoVacante::Cerrada
                && ! $postulacion->estaRetirada()
                && $this->usuario()->can('update', $postulacion),
            'puedeEvaluar' => $this->puedeEvaluar($postulacion),
            'etapas' => EtapaPostulacion::opciones(),
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

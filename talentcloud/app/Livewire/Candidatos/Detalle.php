<?php

namespace App\Livewire\Candidatos;

use App\Enums\EstadoVacante;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Candidatos\CompetenciasDelCandidato;
use App\Services\Cvs\SubirCv;
use App\Services\Postulaciones\PostularCandidato;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** Ficha del candidato: CV, competencias y postulaciones en la empresa. */
class Detalle extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $candidatoId;

    public string $nuevaCompetencia = '';

    public string $vacanteId = '';

    /** @var TemporaryUploadedFile|null */
    public $archivoCv = null;

    public function mount(Candidato $candidato): void
    {
        // El candidato llega sin filtro (es global): la visibilidad la decide la Policy.
        $this->authorize('view', $candidato);

        $this->candidatoId = $candidato->id;
    }

    public function agregarCompetencia(CompetenciasDelCandidato $competencias): void
    {
        $candidato = $this->candidato();
        $this->authorize('update', $candidato);

        $this->validate(['nuevaCompetencia' => ['required', 'string', 'max:120']], attributes: ['nuevaCompetencia' => 'competencia']);

        $competencias->agregar($candidato, trim($this->nuevaCompetencia));
        $this->reset('nuevaCompetencia');
    }

    public function quitarCompetencia(int $competenciaId, CompetenciasDelCandidato $competencias): void
    {
        $candidato = $this->candidato();
        $this->authorize('update', $candidato);

        $competencias->quitar($candidato, $competenciaId);
    }

    public function subirCv(SubirCv $cvs): void
    {
        $candidato = $this->candidato();
        $this->authorize('update', $candidato);

        $this->validate([
            'archivoCv' => ['required', 'file', 'mimes:'.implode(',', config('talentcloud.cvs.mimes')), 'max:'.config('talentcloud.cvs.max_kb')],
        ], attributes: ['archivoCv' => 'CV']);

        /** @var User $user */
        $user = auth()->user();

        if ($this->archivoCv instanceof TemporaryUploadedFile) {
            $cvs->ejecutar($candidato, $this->archivoCv, $user);
        }

        $this->reset('archivoCv');
    }

    public function reintentar(SubirCv $cvs): void
    {
        $cv = $this->candidato()->cvActual;
        abort_if($cv === null, 404);
        $this->authorize('reintentar', $cv);

        $cvs->reintentar($cv);
    }

    public function postular(PostularCandidato $postular): void
    {
        $candidato = $this->candidato();
        $this->authorize('update', $candidato);

        $this->validate(['vacanteId' => ['required', 'integer']], attributes: ['vacanteId' => 'vacante']);

        // Con el filtro por empresa, solo se encuentran vacantes propias.
        $vacante = Vacante::find((int) $this->vacanteId);

        if ($vacante === null) {
            $this->addError('vacanteId', 'Elige una vacante abierta de tu empresa.');

            return;
        }

        try {
            $postular->ejecutar($vacante, $candidato);
        } catch (OperacionNoPermitida $e) {
            $this->addError('vacanteId', $e->getMessage());

            return;
        }

        $this->reset('vacanteId');
    }

    public function render(): View
    {
        $candidato = $this->candidato()->load([
            'cvActual',
            'competencias' => fn ($q) => $q->orderBy('nombre'),
            // Solo las postulaciones de la empresa del contexto (admin: todas).
            'postulaciones' => fn ($q) => $q->with('vacante')->latest(),
        ]);

        $postuladas = $candidato->postulaciones->pluck('vacante_id')->all();

        return view('livewire.candidatos.detalle', [
            'candidato' => $candidato,
            'puedeEditar' => auth()->user()?->can('update', $candidato) ?? false,
            'vacantesDisponibles' => Vacante::where('estado', EstadoVacante::Abierta)
                ->whereNotIn('id', $postuladas)
                ->orderBy('titulo')
                ->pluck('titulo', 'id')
                ->all(),
            'catalogo' => Competencia::orderBy('nombre')->pluck('nombre'),
            // "Notas del equipo": una por postulación de la empresa (nunca para el postulante).
            'conNotas' => $candidato->postulaciones->filter(fn (Postulacion $p) => auth()->user()?->can('gestionarNotas', $p) ?? false)->values(),
        ])->title($candidato->nombre);
    }

    private function candidato(): Candidato
    {
        $candidato = Candidato::find($this->candidatoId);

        abort_if($candidato === null, 404);

        return $candidato;
    }
}

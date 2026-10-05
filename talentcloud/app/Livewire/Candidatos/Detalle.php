<?php

namespace App\Livewire\Candidatos;

use App\Enums\EstadoVacante;
use App\Enums\FuenteCompetencia;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Candidatos\CompetenciasDelCandidato;
use App\Services\Cvs\SubirCv;
use App\Services\Postulaciones\PostularCandidato;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Support\OpcionesPorEmpresa;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Ficha del candidato: CV, competencias y postulaciones en la empresa.
 *
 * Para el admin SaaS (vista global) el candidato puede tener procesos en
 * varias empresas: se agrupan por empresa, igual que las competencias que
 * anotó cada una; al anotar o quitar una, el admin indica de qué empresa.
 */
class Detalle extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $candidatoId;

    public string $nuevaCompetencia = '';

    /** Vista global (admin SaaS): empresa para la que se anota la competencia. */
    public string $empresaCompetencia = '';

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

        $global = $this->vistaGlobal();

        $this->validate([
            'nuevaCompetencia' => ['required', 'string', 'max:120'],
            'empresaCompetencia' => $global ? ['required', 'integer'] : ['nullable'],
        ], attributes: ['nuevaCompetencia' => 'competencia', 'empresaCompetencia' => 'empresa']);

        try {
            $competencias->agregar($candidato, trim($this->nuevaCompetencia), $global ? (int) $this->empresaCompetencia : null);
        } catch (OperacionNoPermitida $e) {
            $this->addError($e->campo ?? 'nuevaCompetencia', $e->getMessage());

            return;
        }

        $this->reset('nuevaCompetencia');
    }

    /** $empresaId: en la vista global, la empresa que la anotó (null = sin empresa). */
    public function quitarCompetencia(int $competenciaId, CompetenciasDelCandidato $competencias, ?int $empresaId = null): void
    {
        $candidato = $this->candidato();
        $this->authorize('update', $candidato);

        try {
            $competencias->quitar($candidato, $competenciaId, $this->vistaGlobal() ? $empresaId : null);
        } catch (OperacionNoPermitida $e) {
            $this->addError('nuevaCompetencia', $e->getMessage());
        }
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
            'postulaciones' => fn ($q) => $q->with('vacante.empresa:id,nombre')->latest(),
        ]);

        $postuladas = $candidato->postulaciones->pluck('vacante_id')->all();
        $global = $this->vistaGlobal();

        return view('livewire.candidatos.detalle', [
            'candidato' => $candidato,
            'vistaGlobal' => $global,
            // Vista global: procesos, competencias anotadas a mano y notas, agrupados por empresa.
            'procesosPorEmpresa' => $global ? $candidato->postulaciones->groupBy(fn (Postulacion $p) => $p->vacante?->empresa->nombre ?? 'Sin empresa')->sortKeys() : collect(),
            'manualesPorEmpresa' => $global ? $this->manualesPorEmpresa($candidato) : collect(),
            'empresasDelCandidato' => $global
                ? $candidato->postulaciones->mapWithKeys(fn (Postulacion $p) => [$p->empresa_id => (string) $p->vacante?->empresa?->nombre])->sort()->all()
                : [],
            'puedeEditar' => auth()->user()?->can('update', $candidato) ?? false,
            // En la vista global (admin SaaS), agrupadas por empresa.
            'vacantesDisponibles' => OpcionesPorEmpresa::de(
                Vacante::with('empresa:id,nombre')
                    ->where('estado', EstadoVacante::Abierta)
                    ->whereNotIn('id', $postuladas)
                    ->orderBy('titulo')
                    ->get(['id', 'titulo', 'empresa_id']),
                fn (Vacante $v) => $v,
                fn (Vacante $v) => $v->titulo,
            ),
            'catalogo' => Competencia::orderBy('nombre')->pluck('nombre'),
            // "Notas del equipo": una por postulación de la empresa (nunca para el postulante).
            'conNotas' => $candidato->postulaciones
                ->filter(fn (Postulacion $p) => auth()->user()?->can('gestionarNotas', $p) ?? false)
                ->sortBy(fn (Postulacion $p) => $p->vacante?->empresa?->nombre)
                ->values(),
        ])->title($candidato->nombre);
    }

    private function vistaGlobal(): bool
    {
        return app(TenantContext::class)->esGlobal();
    }

    /**
     * Competencias anotadas a mano, por empresa (ordenadas por nombre de empresa).
     *
     * @return Collection<int, array{empresaId: int|null, empresa: string, competencias: Collection<int, Competencia>}>
     */
    private function manualesPorEmpresa(Candidato $candidato): Collection
    {
        $manuales = $candidato->competencias->filter(fn (Competencia $c) => data_get($c, 'pivot.fuente') === FuenteCompetencia::Manual->value);
        $nombres = Empresa::whereIn('id', $manuales->map(fn (Competencia $c) => data_get($c, 'pivot.empresa_id'))->filter()->unique())->pluck('nombre', 'id');

        /** @var list<array{empresaId: int|null, empresa: string, competencias: Collection<int, Competencia>}> $grupos */
        $grupos = [];

        // empresa_id nulo (anotada sin empresa por la plataforma) se agrupa como 0.
        foreach ($manuales->groupBy(fn (Competencia $c) => (int) data_get($c, 'pivot.empresa_id')) as $empresaId => $grupo) {
            $grupos[] = [
                'empresaId' => $empresaId === 0 ? null : (int) $empresaId,
                'empresa' => $empresaId === 0 ? 'Plataforma (sin empresa)' : (string) ($nombres[$empresaId] ?? 'Empresa eliminada'),
                'competencias' => $grupo->values(),
            ];
        }

        return collect($grupos)->sortBy('empresa')->values();
    }

    private function candidato(): Candidato
    {
        $candidato = Candidato::find($this->candidatoId);

        abort_if($candidato === null, 404);

        return $candidato;
    }
}

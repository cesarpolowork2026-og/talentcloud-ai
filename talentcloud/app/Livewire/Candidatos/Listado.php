<?php

namespace App\Livewire\Candidatos;

use App\DTO\NuevoCandidatoData;
use App\Enums\EstadoVacante;
use App\Exports\CandidatosExport;
use App\Livewire\Concerns\WithListado;
use App\Models\Candidato;
use App\Models\Cv;
use App\Models\User;
use App\Models\Vacante;
use App\Repositories\Contracts\CandidatoRepository;
use App\Services\Candidatos\RegistrarCandidato;
use App\Services\Cvs\SubirCv;
use App\Services\Postulaciones\PostularCandidato;
use App\Services\Vacantes\BuscadorDeVacantes;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Pantalla "Candidatos" (prototipo: isCandidatos). */
#[Title('Candidatos')]
class Listado extends Component
{
    use WithFileUploads, WithListado;

    // Diálogo del CV (prototipo: openCv).
    public bool $mostrarDetalle = false;

    #[Locked]
    public ?int $candidatoSeleccionado = null;

    // "+ Nuevo candidato".
    public bool $mostrarFormulario = false;

    public string $nombre = '';

    public string $email = '';

    public string $telefono = '';

    public string $titular = '';

    public string $vacanteId = '';

    /** @var TemporaryUploadedFile|null */
    public $archivoCv = null;

    public bool $autorizado = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Candidato::class);
    }

    /**
     * También por competencia: las anotadas por la empresa en curso (la relación
     * ya oculta las de otras empresas).
     *
     * @return list<string>
     */
    protected function camposBusqueda(): array
    {
        return ['nombre', 'titular', 'competencias.nombre'];
    }

    /** @return array<string, string> */
    protected function opcionesOrden(): array
    {
        return ['nombre' => 'Nombre', 'reciente' => 'Postulación más reciente'];
    }

    protected function columnaDeOrden(string $clave): string
    {
        return $clave === 'reciente' ? 'ultima_postulacion_at' : 'candidatos.nombre';
    }

    protected function porPagina(): int
    {
        return 12;
    }

    public function verDetalle(int $id): void
    {
        $candidato = $this->candidato($id);
        $this->authorize('view', $candidato);

        $this->candidatoSeleccionado = $candidato->id;
        $this->mostrarDetalle = true;
    }

    public function reintentar(int $cvId, SubirCv $cvs): void
    {
        $cv = Cv::with('candidato')->find($cvId);
        abort_if($cv === null, 404);
        $this->authorize('reintentar', $cv);

        $cvs->reintentar($cv);
    }

    public function abrirFormulario(): void
    {
        $this->authorize('create', Candidato::class);

        $this->resetValidation();
        $this->reset('nombre', 'email', 'telefono', 'titular', 'vacanteId', 'archivoCv', 'autorizado');
        $this->mostrarFormulario = true;
    }

    public function registrar(RegistrarCandidato $registrar, SubirCv $cvs, PostularCandidato $postular): void
    {
        $this->authorize('create', Candidato::class);

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'titular' => ['nullable', 'string', 'max:120'],
            'vacanteId' => ['nullable', 'integer'],
            'archivoCv' => ['nullable', 'file', 'mimes:'.implode(',', config('talentcloud.cvs.mimes')), 'max:'.config('talentcloud.cvs.max_kb')],
            'autorizado' => ['accepted'],
        ], [
            'autorizado.accepted' => 'Confirma que el candidato autorizó el tratamiento de sus datos.',
        ], [
            'archivoCv' => 'CV',
            'vacanteId' => 'vacante',
        ]);

        /** @var User $user */
        $user = auth()->user();

        $vacante = $datos['vacanteId'] ? Vacante::find((int) $datos['vacanteId']) : null;

        if ($datos['vacanteId'] && $vacante === null) {
            $this->addError('vacanteId', 'Elige una vacante abierta de tu empresa.');

            return;
        }

        try {
            DB::transaction(function () use ($registrar, $cvs, $postular, $datos, $user, $vacante): void {
                $candidato = $registrar->ejecutar($user->empresa, new NuevoCandidatoData(
                    nombre: $datos['nombre'],
                    email: $datos['email'] ?: null,
                    telefono: $datos['telefono'] ?: null,
                    titular: $datos['titular'] ?: null,
                    autorizoTratamientoDeDatos: true,
                ));

                if ($this->archivoCv instanceof TemporaryUploadedFile) {
                    $cvs->ejecutar($candidato, $this->archivoCv, $user);
                }

                if ($vacante !== null) {
                    $postular->ejecutar($vacante, $candidato);
                }
            });
        } catch (OperacionNoPermitida $e) {
            $this->addError('vacanteId', $e->getMessage());

            return;
        }

        $this->mostrarFormulario = false;
        $this->reset('nombre', 'email', 'telefono', 'titular', 'vacanteId', 'archivoCv', 'autorizado');
    }

    /** "Exportar a Excel": lo que se ve, con la búsqueda y los filtros vigentes. */
    public function exportar(CandidatoRepository $candidatos): BinaryFileResponse
    {
        $this->authorize('viewAny', Candidato::class);

        return Excel::download(new CandidatosExport($this->filtrar($candidatos->consultaListado())), 'candidatos-'.now()->format('Y-m-d').'.xlsx');
    }

    /**
     * Buscador de «Postular a»: hasta 20 vacantes abiertas, no todas las de la empresa..
     *
     * @return list<array{id: int, etiqueta: string, detalle: string|null}>
     */
    #[Renderless]
    public function buscarVacantesAbiertas(string $texto, BuscadorDeVacantes $buscador): array
    {
        $this->authorize('create', Candidato::class);

        return $buscador->buscar($texto, base: Vacante::query()->where('estado', EstadoVacante::Abierta));
    }

    public function render(CandidatoRepository $candidatos): View
    {
        // La ficha se lee solo con el diálogo abierto y sin la subconsulta de orden.
        $seleccionado = $this->candidatoSeleccionado && $this->mostrarDetalle
            ? $candidatos->consultaListado(conOrdenReciente: false)->whereKey($this->candidatoSeleccionado)->first()
            : null;

        return view('livewire.candidatos.listado', [
            'filas' => $this->listar($candidatos->consultaListado()),
            'seleccionado' => $seleccionado,
            // Admin SaaS: cada candidato con sus procesos agrupados por empresa.
            'vistaGlobal' => app(TenantContext::class)->esGlobal(),
            'puedeRegistrar' => auth()->user()?->can('create', Candidato::class) ?? false,
        ]);
    }

    private function candidato(int $id): Candidato
    {
        $candidato = app(CandidatoRepository::class)->buscarVisible($id);

        abort_if($candidato === null, 404);

        return $candidato;
    }
}

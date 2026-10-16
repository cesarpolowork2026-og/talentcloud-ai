<?php

namespace App\Livewire\Vacantes;

use App\DTO\NuevaVacanteData;
use App\Enums\EstadoVacante;
use App\Enums\Modalidad;
use App\Enums\Rol;
use App\Exports\VacantesExport;
use App\Livewire\Concerns\WithListado;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\User;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;
use App\Services\Vacantes\CrearVacante;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Pantalla "Vacantes" (prototipo: isVacantes). */
#[Title('Vacantes')]
class Listado extends Component
{
    use WithListado;

    // Formulario "Nueva vacante".
    public bool $mostrarFormulario = false;

    public string $titulo = '';

    public string $empresaId = '';

    public string $ubicacion = '';

    public string $modalidad = 'remoto';

    /** @var list<string> */
    public array $competencias = [];

    // Vista previa (diálogo del prototipo al pulsar una fila).
    public bool $mostrarVistaPrevia = false;

    #[Locked]
    public ?int $vacanteSeleccionada = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Vacante::class);

        // Acceso directo desde el onboarding ("Crear vacante").
        if (request()->boolean('nueva')) {
            $this->abrirFormulario();
        }
    }

    /** @return list<string> */
    protected function camposBusqueda(): array
    {
        return ['titulo', 'empresa.nombre'];
    }

    /** @return array<string, string> Orden de los chips del prototipo. */
    protected function opcionesEstado(): array
    {
        $orden = [EstadoVacante::Abierta, EstadoVacante::EnRevision, EstadoVacante::Borrador, EstadoVacante::Cerrada];

        return array_combine(
            array_map(fn (EstadoVacante $e) => $e->value, $orden),
            array_map(fn (EstadoVacante $e) => $e->etiqueta(), $orden),
        );
    }

    /** @return array<string, string> */
    protected function opcionesOrden(): array
    {
        return [
            'titulo' => 'Vacante',
            'candidatos' => 'Candidatos',
            'creada' => 'Fecha',
        ];
    }

    protected function columnaDeOrden(string $clave): string
    {
        return match ($clave) {
            'candidatos' => 'postulaciones_count',
            'creada' => 'vacantes.created_at',
            default => 'titulo',
        };
    }

    public function verVistaPrevia(int $id): void
    {
        $vacante = $this->vacante($id);
        $this->authorize('view', $vacante);

        $this->vacanteSeleccionada = $vacante->id;
        $this->mostrarVistaPrevia = true;
    }

    public function abrirFormulario(): void
    {
        $this->authorize('create', Vacante::class);

        $this->resetValidation();
        $this->reset('titulo', 'empresaId', 'ubicacion', 'modalidad', 'competencias');
        $this->mostrarFormulario = true;
    }

    /** Al pasar a remoto el lugar deja de ser una ubicación: se limpia para no arrastrar «Lima». */
    public function updatedModalidad(string $valor): void
    {
        if (Modalidad::tryFrom($valor) === Modalidad::Remoto) {
            $this->reset('ubicacion');
        }

        $this->resetValidation('ubicacion');
    }

    public function crear(CrearVacante $crear, VacanteRepository $vacantes): void
    {
        $this->authorize('create', Vacante::class);

        $esAdmin = $this->usuario()->tieneRol(Rol::Admin);

        $datos = $this->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'empresaId' => [$esAdmin ? 'required' : 'nullable', 'integer', Rule::exists('empresas', 'id')],
            'ubicacion' => ['nullable', 'string', 'max:120', Rule::requiredIf($this->modalidad !== Modalidad::Remoto->value)],
            'modalidad' => ['required', Rule::enum(Modalidad::class)],
            'competencias' => ['required', 'array', 'min:1', 'max:20'],
            'competencias.*' => ['string', 'max:80'],
        ], messages: [
            'ubicacion.required' => 'Indica dónde se trabajará: una vacante presencial o híbrida necesita ubicación.',
            'competencias.required' => 'Agrega al menos una competencia.',
            'competencias.min' => 'Agrega al menos una competencia.',
        ], attributes: [
            'titulo' => 'título del puesto',
            'empresaId' => 'empresa',
            'competencias' => 'competencias requeridas',
        ]);

        $empresa = $esAdmin
            ? $vacantes->empresasDisponibles()->firstWhere('id', (int) $datos['empresaId'])
            : $this->usuario()->empresa;

        if (! $empresa instanceof Empresa) {
            $this->addError('empresaId', 'Elige una empresa activa.');

            return;
        }

        $listaCompetencias = NuevaVacanteData::depurarCompetencias($datos['competencias']);

        if ($listaCompetencias === []) {
            $this->addError('competencias', 'Agrega al menos una competencia.');

            return;
        }

        try {
            $vacante = $crear->ejecutar($empresa, new NuevaVacanteData(
                titulo: $datos['titulo'],
                ubicacion: $datos['ubicacion'] ?: null,
                modalidad: Modalidad::from($datos['modalidad']),
                competencias: $listaCompetencias,
            ), $this->usuario());
        } catch (OperacionNoPermitida $e) {
            $this->addError($e->campo ?? 'titulo', $e->getMessage());

            return;
        }

        $this->redirectRoute('vacantes.show', $vacante, navigate: true);
    }

    /** "Exportar a Excel": lo que se ve, con la búsqueda y los filtros vigentes. */
    public function exportar(VacanteRepository $vacantes): BinaryFileResponse
    {
        $this->authorize('viewAny', Vacante::class);

        return Excel::download(new VacantesExport($this->filtrar($vacantes->consultaListado())), 'vacantes-'.now()->format('Y-m-d').'.xlsx');
    }

    public function render(VacanteRepository $vacantes): View
    {
        $esAdmin = $this->usuario()->tieneRol(Rol::Admin);
        $seleccionada = $this->vacanteSeleccionada ? $vacantes->buscar($this->vacanteSeleccionada) : null;

        return view('livewire.vacantes.listado', [
            'filas' => $this->listar($vacantes->consultaListado()),
            'seleccionada' => $seleccionada?->load('empresa')->loadCount('postulaciones'),
            'esAdmin' => $esAdmin,
            'subtitulo' => $esAdmin
                ? 'Todas las vacantes publicadas por las empresas de la plataforma.'
                : 'Vacantes publicadas por '.$this->usuario()->empresa?->nombre.'.',
            'empresas' => $esAdmin ? $vacantes->empresasDisponibles()->pluck('nombre', 'id')->all() : [],
            'modalidades' => Modalidad::opciones(),
            // El selector de competencias busca en el navegador: el catálogo viaja solo con el formulario abierto.
            'catalogo' => $this->mostrarFormulario
                ? Competencia::catalogoCompleto()
                : [],
        ]);
    }

    private function vacante(int $id): Vacante
    {
        $vacante = app(VacanteRepository::class)->buscar($id);

        abort_if($vacante === null, 404);

        return $vacante;
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}

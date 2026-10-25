<?php

namespace App\Livewire\Entrevistas;

use App\DTO\NuevaEntrevistaData;
use App\Enums\EstadoEntrevista;
use App\Enums\ModalidadEntrevista;
use App\Enums\Rol;
use App\Livewire\Concerns\SeActualizaSola;
use App\Livewire\Concerns\WithListado;
use App\Livewire\Forms\ReprogramacionForm;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Repositories\Contracts\EntrevistaRepository;
use App\Repositories\Contracts\EquipoRepository;
use App\Services\Entrevistas\CambiarEstadoEntrevista;
use App\Services\Entrevistas\ProgramarEntrevista;
use App\Services\Entrevistas\ReprogramarEntrevista;
use App\Services\Entrevistas\ResumenDeAgenda;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Support\Formato;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Pantalla "Entrevistas" (prototipo: isEntrevistas) + programar, confirmar y completar. */
#[Title('Entrevistas')]
class Listado extends Component
{
    use SeActualizaSola;
    use WithListado {
        hayFiltros as private hayFiltrosDelListado;
    }

    /** Solo las entrevistas que ya terminaron y siguen abiertas (por cerrar). */
    #[Url(as: 'vencidas', except: false)]
    public bool $vencidas = false;

    public const DURACIONES = [30 => '30 min', 45 => '45 min', 60 => '1 hora', 90 => '1 h 30 min'];

    // Detalle y cancelación (diálogos del prototipo).
    public bool $mostrarDetalle = false;

    public bool $mostrarCancelacion = false;

    #[Locked]
    public ?int $entrevistaSeleccionada = null;

    // Formulario "Programar entrevista".
    public bool $mostrarFormulario = false;

    public string $postulacionId = '';

    public string $entrevistadorId = '';

    public string $iniciaAt = '';

    public string $duracionMin = '45';

    public string $modalidad = 'videollamada';

    public string $enlace = '';

    /** Dirección (presencial) o teléfono/indicación (telefónica). */
    public string $lugar = '';

    public string $notas = '';

    /** "Mover a la etapa Entrevista": marcado por defecto (el Kanban debe reflejar la entrevista). */
    public bool $moverAEntrevista = true;

    // "Reprogramar" (sobre la entrevista seleccionada).
    public bool $mostrarReprogramacion = false;

    public ReprogramacionForm $reprogramacion;

    public function mount(): void
    {
        $this->authorize('viewAny', Entrevista::class);

        // Acceso directo desde el Kanban: "Programar entrevista".
        if (request()->filled('programar')) {
            $this->abrirFormulario(request()->integer('programar'));
        }
    }

    /** @return list<string> */
    protected function camposBusqueda(): array
    {
        return ['postulacion.candidato.nombre', 'postulacion.vacante.titulo'];
    }

    /**
     * Filtro por estado de la entrevista: Pendiente, Confirmada, Completada y Cancelada, en el orden del enum.
     *
     * @return array<string, string>
     */
    protected function opcionesEstado(): array
    {
        return EstadoEntrevista::opciones();
    }

    /** Una búsqueda, un estado o «por cerrar» elegidos: sin resultados con filtros no es lo mismo que una agenda vacía. */
    public function hayFiltros(): bool
    {
        return $this->vencidas || $this->hayFiltrosDelListado();
    }

    public function alternarVencidas(): void
    {
        $this->vencidas = ! $this->vencidas;
        $this->resetPage();
    }

    public function verDetalle(int $id): void
    {
        $entrevista = $this->entrevista($id);
        $this->authorize('view', $entrevista);

        $this->entrevistaSeleccionada = $entrevista->id;
        $this->mostrarDetalle = true;
    }

    public function confirmar(int $id, CambiarEstadoEntrevista $cambiar): void
    {
        $this->cambiarEstado($id, fn (Entrevista $e) => $cambiar->confirmar($e));
    }

    public function completar(int $id, CambiarEstadoEntrevista $cambiar): void
    {
        $this->cambiarEstado($id, fn (Entrevista $e) => $cambiar->completar($e));
    }

    public function confirmarCancelacion(int $id): void
    {
        $entrevista = $this->entrevista($id);
        $this->authorize('update', $entrevista);

        $this->entrevistaSeleccionada = $entrevista->id;
        $this->mostrarDetalle = false;
        $this->mostrarCancelacion = true;
    }

    public function cancelar(CambiarEstadoEntrevista $cambiar): void
    {
        if ($this->entrevistaSeleccionada !== null) {
            $this->cambiarEstado($this->entrevistaSeleccionada, fn (Entrevista $e) => $cambiar->cancelar($e));
        }
        $this->mostrarCancelacion = false;
    }

    public function abrirFormulario(?int $postulacionId = null): void
    {
        $this->authorize('create', Entrevista::class);

        $this->resetValidation();
        $this->reset('postulacionId', 'entrevistadorId', 'iniciaAt', 'duracionMin', 'modalidad', 'enlace', 'lugar', 'notas', 'moverAEntrevista');
        $this->postulacionId = $postulacionId ? (string) $postulacionId : '';
        // Por defecto, quien programa es el entrevistador (si es de la empresa).
        $this->entrevistadorId = $this->usuario()->empresa_id !== null ? (string) $this->usuario()->id : '';
        $this->mostrarFormulario = true;
    }

    /** Al cambiar de candidato, el entrevistador elegido puede no ser de su empresa (admin SaaS). */
    public function updatedPostulacionId(): void
    {
        if (! array_key_exists((int) $this->entrevistadorId, $this->opcionesDeEntrevistador())) {
            $this->entrevistadorId = '';
        }
    }

    public function programar(ProgramarEntrevista $programar): void
    {
        $this->authorize('create', Entrevista::class);

        $datos = $this->validate([
            'postulacionId' => ['required', 'integer'],
            'entrevistadorId' => ['required', 'integer'],
            'iniciaAt' => ['required', 'date_format:Y-m-d\TH:i'],
            'duracionMin' => ['required', Rule::in(array_keys(self::DURACIONES))],
            'modalidad' => ['required', Rule::enum(ModalidadEntrevista::class)],
            'enlace' => ['nullable', 'url:https,http', 'max:500'],
            'lugar' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'postulacionId' => 'candidato',
            'entrevistadorId' => 'entrevistador',
            'iniciaAt' => 'fecha y hora',
            'duracionMin' => 'duración',
        ]);

        try {
            $programar->ejecutar(new NuevaEntrevistaData(
                postulacionId: (int) $datos['postulacionId'],
                entrevistadorId: (int) $datos['entrevistadorId'],
                // La hora se escribe en la zona de la aplicación (America/Lima).
                iniciaAt: CarbonImmutable::createFromFormat('Y-m-d\TH:i', $datos['iniciaAt'], config('app.timezone')) ?: now()->toImmutable(),
                duracionMin: (int) $datos['duracionMin'],
                modalidad: ModalidadEntrevista::from($datos['modalidad']),
                enlace: $datos['enlace'] ?: null,
                notas: $datos['notas'] ?: null,
                moverAEntrevista: $this->moverAEntrevista,
                lugar: $datos['lugar'] ?: null,
            ), $this->usuario());
        } catch (OperacionNoPermitida $e) {
            $this->addError($e->campo ?? 'postulacionId', $e->getMessage());

            return;
        }

        $this->mostrarFormulario = false;
        $this->resetPage();
    }

    public function abrirReprogramacion(int $id): void
    {
        $entrevista = $this->entrevista($id);
        $this->authorize('update', $entrevista);

        $this->entrevistaSeleccionada = $entrevista->id;
        $this->reprogramacion->cargar($entrevista);
        $this->mostrarDetalle = false;
        $this->mostrarReprogramacion = true;
    }

    public function reprogramar(ReprogramarEntrevista $reprogramar): void
    {
        abort_if($this->entrevistaSeleccionada === null, 404);
        $entrevista = $this->entrevista($this->entrevistaSeleccionada);
        $this->authorize('update', $entrevista);

        $datos = $this->reprogramacion->datos();

        try {
            $reprogramar->ejecutar($entrevista, $datos);
        } catch (OperacionNoPermitida $e) {
            // La fecha y el lugar tienen su campo; el resto de reglas (estado, candidato, entrevistador) va arriba del formulario.
            $this->addError(match ($e->campo) {
                'iniciaAt' => 'reprogramacion.iniciaAt',
                'lugar' => 'reprogramacion.lugar',
                default => 'reprogramacion',
            }, $e->getMessage());

            return;
        }

        $this->mostrarReprogramacion = false;
    }

    /**
     * Buscador de «Candidato y vacante»: hasta 20 postulaciones donde se puede agendar..
     *
     * @return list<array{id: int, etiqueta: string, detalle: string|null}>
     */
    #[Renderless]
    public function buscarPostulaciones(string $texto, EntrevistaRepository $entrevistas): array
    {
        $this->authorize('create', Entrevista::class);

        return $entrevistas->postulacionesAgendables($texto)
            ->map(fn (Postulacion $p) => ['id' => $p->id, 'etiqueta' => $this->etiquetaDePostulacion($p), 'detalle' => $p->etapa->etiqueta()])
            ->all();
    }

    private function etiquetaDePostulacion(?Postulacion $p): string
    {
        return $p === null ? '' : $p->candidato?->nombre.' — '.Formato::vacante($p->vacante);
    }

    /** Entrevistas que otra persona programa, reprograma, confirma, completa o cancela (y postulaciones retiradas). */
    protected function partesDeLaFirma(): array
    {
        return $this->partesDeModelos([Entrevista::class, Postulacion::class]);
    }

    public function render(EntrevistaRepository $entrevistas, ResumenDeAgenda $agenda): View
    {
        $seleccionada = $this->entrevistaSeleccionada ? $entrevistas->buscar($this->entrevistaSeleccionada) : null;

        // La postulación elegida en el formulario se lee una sola vez (antes, tres veces por render).
        $elegida = $this->mostrarFormulario && ctype_digit($this->postulacionId)
            ? Postulacion::with(['candidato:id,nombre', 'vacante.empresa:id,nombre', 'empresa'])->find((int) $this->postulacionId)
            : null;

        return view('livewire.entrevistas.listado', [
            'resumen' => $agenda->calcular(),
            'filas' => $this->listar($entrevistas->consultaListado()->when($this->vencidas, fn ($q) => $q->porCerrar())->orderBy('inicia_at')),
            // Admin SaaS: columna "Empresa" (ve las entrevistas de todas).
            'vistaGlobal' => app(TenantContext::class)->esGlobal(),
            'seleccionada' => $seleccionada,
            // El texto del buscador cuando ya hay una postulación elegida (por ejemplo, desde «Programar entrevista» del Kanban).
            'etiquetaDePostulacion' => $this->etiquetaDePostulacion($elegida),
            // La casilla "Mover a Entrevista" solo aparece si el candidato elegido aún no llegó a esa etapa.
            'puedeMoverAEntrevista' => $elegida?->etapa->antesDeEntrevista() ?? false,
            'entrevistadores' => $this->mostrarFormulario ? $this->opcionesDeEntrevistador($elegida) : [],
            'modalidades' => ModalidadEntrevista::opciones(),
            'duraciones' => self::DURACIONES,
        ]);
    }

    /**
     * Personal activo de la empresa de la postulación elegida (o de la del usuario).
     *
     * @return array<int, string>
     */
    private function opcionesDeEntrevistador(?Postulacion $elegida = null): array
    {
        // Sin la postulación ya leída (al cambiar de candidato, fuera del render) se busca aquí.
        $elegida ??= ctype_digit($this->postulacionId) ? Postulacion::with('empresa')->find((int) $this->postulacionId) : null;

        $empresa = $this->postulacionId !== ''
            ? $elegida?->empresa
            : $this->usuario()->empresa;

        if ($empresa === null) {
            return [];
        }

        return app(EquipoRepository::class)->usuariosDe($empresa)
            ->filter(fn (User $u) => $u->estaActivo())
            ->mapWithKeys(fn (User $u) => [$u->id => $u->name])
            ->all();
    }

    /**
     * @param  Closure(Entrevista): Entrevista  $accion
     */
    private function cambiarEstado(int $id, Closure $accion): void
    {
        $entrevista = $this->entrevista($id);
        $this->authorize('update', $entrevista);

        try {
            $accion($entrevista);
        } catch (OperacionNoPermitida $e) {
            $this->addError('entrevista', $e->getMessage());
        }
    }

    private function entrevista(int $id): Entrevista
    {
        $entrevista = app(EntrevistaRepository::class)->buscar($id);

        abort_if($entrevista === null, 404);

        return $entrevista;
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    /** El admin SaaS no tiene empresa: elige primero la postulación y después al entrevistador. */
    public function esAdmin(): bool
    {
        return $this->usuario()->tieneRol(Rol::Admin);
    }
}

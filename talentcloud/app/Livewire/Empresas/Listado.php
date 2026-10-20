<?php

namespace App\Livewire\Empresas;

use App\DTO\NuevaEmpresaData;
use App\Enums\EstadoEmpresa;
use App\Enums\PlanEmpresa;
use App\Livewire\Concerns\WithListado;
use App\Models\Empresa;
use App\Models\User;
use App\Repositories\Contracts\EmpresaRepository;
use App\Repositories\Contracts\EquipoRepository;
use App\Services\Empresas\CambiarEstadoEmpresa;
use App\Services\Empresas\CambiarPlanEmpresa;
use App\Services\Empresas\CrearEmpresa;
use App\Services\Empresas\LimitesDelPlan;
use App\Services\Equipo\InvitacionNoValida;
use App\Services\Plataforma\RegistroDeAuditoria;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Pantalla "Empresas" del admin SaaS (prototipo: isEmpresas). */
#[Title('Empresas')]
class Listado extends Component
{
    use WithListado;

    // Formulario "Nueva empresa".
    public bool $mostrarFormulario = false;

    public string $nombre = '';

    public string $plan = 'pro';

    public string $emailAdministrador = '';

    // Diálogo de detalle y confirmación de Suspender / Activar.
    public bool $mostrarDetalle = false;

    public bool $mostrarConfirmacion = false;

    // Cambiar plan.
    public bool $mostrarCambioDePlan = false;

    public string $planNuevo = '';

    #[Locked]
    public ?int $empresaSeleccionada = null;

    /** Lo que se confirmó al abrir el diálogo: suspender (true) o activar (false). */
    #[Locked]
    public bool $suspenderSeleccionada = true;

    public function mount(): void
    {
        $this->authorize('viewAny', Empresa::class);

        // «Aplicar plan» del panel de la plataforma: /empresas?cambiar-plan={id} abre el cambio con el plan solicitado propuesto.
        // /empresas?ver={id} (enlaces del panel): abre el detalle de esa empresa.
        $ver = request()->query('ver');
        if (is_numeric($ver)) {
            $this->verDetalle((int) $ver);
        }

        $cambiar = request()->query('cambiar-plan');
        if (is_numeric($cambiar)) {
            $this->abrirCambioDePlan((int) $cambiar);
        }
    }

    /** @return list<string> */
    protected function camposBusqueda(): array
    {
        return ['nombre'];
    }

    /** @return array<string, string> */
    protected function opcionesEstado(): array
    {
        return EstadoEmpresa::opciones();
    }

    /** @return array<string, string> */
    protected function opcionesOrden(): array
    {
        return [
            'nombre' => 'Nombre',
            'usuarios' => 'Usuarios',
            'vacantes_activas' => 'Vacantes activas',
        ];
    }

    protected function columnaDeOrden(string $clave): string
    {
        return match ($clave) {
            'usuarios' => 'usuarios_count',
            'vacantes_activas' => 'vacantes_activas_count',
            default => 'nombre',
        };
    }

    public function verDetalle(int $id): void
    {
        $this->authorize('view', $empresa = $this->empresa($id));

        $this->empresaSeleccionada = $empresa->id;
        $this->mostrarDetalle = true;
    }

    public function confirmarCambioEstado(int $id): void
    {
        $this->authorize('cambiarEstado', $empresa = $this->empresa($id));

        $this->empresaSeleccionada = $empresa->id;
        $this->suspenderSeleccionada = $empresa->estaActiva();
        $this->mostrarConfirmacion = true;
    }

    public function cambiarEstado(CambiarEstadoEmpresa $cambiar, RegistroDeAuditoria $auditoria): void
    {
        $empresa = $this->empresa((int) $this->empresaSeleccionada);
        $this->authorize('cambiarEstado', $empresa);

        /** @var User $admin */
        $admin = auth()->user();

        if ($this->suspenderSeleccionada) {
            $cambiar->suspender($empresa);
            $auditoria->anotar($admin, RegistroDeAuditoria::EMPRESA_SUSPENDIDA, "Se suspendió la empresa {$empresa->nombre}.", $empresa->id);
        } else {
            $cambiar->activar($empresa);
            $auditoria->anotar($admin, RegistroDeAuditoria::EMPRESA_ACTIVADA, "Se activó la empresa {$empresa->nombre}.", $empresa->id);
        }

        $this->mostrarConfirmacion = false;
    }

    public function abrirCambioDePlan(int $id): void
    {
        $this->authorize('cambiarPlan', $empresa = $this->empresa($id));

        $this->resetValidation();
        $this->empresaSeleccionada = $empresa->id;
        // Si la empresa pidió un plan, viene propuesto: atenderla es aplicarlo.
        $this->planNuevo = ($empresa->plan_solicitado ?? $empresa->plan)->value;
        $this->mostrarDetalle = false;
        $this->mostrarCambioDePlan = true;
    }

    public function cambiarPlan(CambiarPlanEmpresa $cambiar, RegistroDeAuditoria $auditoria): void
    {
        $empresa = $this->empresa((int) $this->empresaSeleccionada);
        $this->authorize('cambiarPlan', $empresa);

        $datos = $this->validate(['planNuevo' => ['required', Rule::enum(PlanEmpresa::class)]], attributes: ['planNuevo' => 'plan']);

        /** @var User $admin */
        $admin = auth()->user();

        $anterior = $empresa->plan->etiqueta();

        try {
            $cambiado = $cambiar->ejecutar($empresa, PlanEmpresa::from($datos['planNuevo']), $admin);
            $auditoria->anotar($admin, RegistroDeAuditoria::PLAN_CAMBIADO, "Se cambió el plan de {$empresa->nombre}: de {$anterior} a {$cambiado->plan->etiqueta()}.", $empresa->id);
        } catch (OperacionNoPermitida $e) {
            $this->addError('planNuevo', $e->getMessage());

            return;
        }

        $this->mostrarCambioDePlan = false;
    }

    public function abrirFormulario(): void
    {
        $this->authorize('create', Empresa::class);

        $this->resetValidation();
        $this->reset('nombre', 'plan', 'emailAdministrador');
        $this->mostrarFormulario = true;
    }

    public function crear(CrearEmpresa $crear, RegistroDeAuditoria $auditoria): void
    {
        $this->authorize('create', Empresa::class);

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('empresas', 'nombre')],
            'plan' => ['required', Rule::enum(PlanEmpresa::class)],
            'emailAdministrador' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ], attributes: [
            'nombre' => 'nombre de la empresa',
            'emailAdministrador' => 'correo del administrador',
        ]);

        /** @var User $admin */
        $admin = auth()->user();

        try {
            $nueva = $crear->ejecutar(new NuevaEmpresaData($datos['nombre'], PlanEmpresa::from($datos['plan']), $datos['emailAdministrador']), $admin);
            $auditoria->anotar($admin, RegistroDeAuditoria::EMPRESA_CREADA, "Se creó la empresa {$nueva->nombre} (plan {$nueva->plan->etiqueta()}).", $nueva->id);
        } catch (InvitacionNoValida $e) {
            $this->addError('emailAdministrador', $e->getMessage());

            return;
        }

        $this->mostrarFormulario = false;
        $this->reset('nombre', 'plan', 'emailAdministrador');
    }

    public function render(EmpresaRepository $empresas, EquipoRepository $equipo, LimitesDelPlan $limites): View
    {
        // La empresa abierta se relee solo mientras alguno de sus diálogos está abierto (no en cada búsqueda o página).
        $hayDialogo = $this->mostrarDetalle || $this->mostrarConfirmacion || $this->mostrarCambioDePlan;
        $seleccionada = $this->empresaSeleccionada && $hayDialogo ? $empresas->buscar($this->empresaSeleccionada) : null;

        return view('livewire.empresas.listado', [
            'filas' => $this->listar($empresas->consultaParaAdministracion()),
            'seleccionada' => $seleccionada ? $empresas->conConteos($seleccionada) : null,
            // Solo lectura: la gestión del equipo es de la propia empresa.
            'usuariosDeLaSeleccionada' => $seleccionada && $this->mostrarDetalle ? $equipo->usuariosDe($seleccionada) : collect(),
            'usoDeLaSeleccionada' => $seleccionada ? $limites->uso($seleccionada) : null,
            'planes' => PlanEmpresa::opciones(),
            'planElegido' => PlanEmpresa::tryFrom($this->planNuevo),
        ]);
    }

    private function empresa(int $id): Empresa
    {
        $empresa = app(EmpresaRepository::class)->buscar($id);

        abort_if($empresa === null, 404);

        return $empresa;
    }
}

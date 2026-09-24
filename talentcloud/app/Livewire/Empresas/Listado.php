<?php

namespace App\Livewire\Empresas;

use App\DTO\NuevaEmpresaData;
use App\Enums\EstadoEmpresa;
use App\Enums\PlanEmpresa;
use App\Livewire\Concerns\WithListado;
use App\Models\Empresa;
use App\Models\User;
use App\Repositories\Contracts\EmpresaRepository;
use App\Services\Empresas\CambiarEstadoEmpresa;
use App\Services\Empresas\CrearEmpresa;
use App\Services\Equipo\InvitacionNoValida;
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

    #[Locked]
    public ?int $empresaSeleccionada = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Empresa::class);
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
        $this->mostrarConfirmacion = true;
    }

    public function cambiarEstado(CambiarEstadoEmpresa $cambiar): void
    {
        $empresa = $this->empresa((int) $this->empresaSeleccionada);
        $this->authorize('cambiarEstado', $empresa);

        $cambiar->ejecutar($empresa);

        $this->mostrarConfirmacion = false;
    }

    public function abrirFormulario(): void
    {
        $this->authorize('create', Empresa::class);

        $this->resetValidation();
        $this->reset('nombre', 'plan', 'emailAdministrador');
        $this->mostrarFormulario = true;
    }

    public function crear(CrearEmpresa $crear): void
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
            $crear->ejecutar(new NuevaEmpresaData($datos['nombre'], PlanEmpresa::from($datos['plan']), $datos['emailAdministrador']), $admin);
        } catch (InvitacionNoValida $e) {
            $this->addError('emailAdministrador', $e->getMessage());

            return;
        }

        $this->mostrarFormulario = false;
        $this->reset('nombre', 'plan', 'emailAdministrador');
    }

    public function render(EmpresaRepository $empresas): View
    {
        $seleccionada = $this->empresaSeleccionada ? $empresas->buscar($this->empresaSeleccionada) : null;

        return view('livewire.empresas.listado', [
            'filas' => $this->listar($empresas->consultaParaAdministracion()),
            'seleccionada' => $seleccionada ? $empresas->conConteos($seleccionada) : null,
            'planes' => array_combine(
                array_column(PlanEmpresa::cases(), 'value'),
                array_map(fn (PlanEmpresa $p) => $p->etiqueta(), PlanEmpresa::cases()),
            ),
        ]);
    }

    private function empresa(int $id): Empresa
    {
        $empresa = app(EmpresaRepository::class)->buscar($id);

        abort_if($empresa === null, 404);

        return $empresa;
    }
}

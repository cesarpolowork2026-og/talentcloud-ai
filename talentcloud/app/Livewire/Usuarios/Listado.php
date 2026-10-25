<?php

namespace App\Livewire\Usuarios;

use App\Enums\Rol;
use App\Livewire\Concerns\SeActualizaSola;
use App\Livewire\Concerns\WithListado;
use App\Models\Invitacion;
use App\Models\User;
use App\Services\Equipo\CambiarEstadoUsuario;
use App\Services\Plataforma\RegistroDeAuditoria;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * «Usuarios» del admin SaaS: todas las cuentas de la plataforma, de cualquier empresa, con su rol y su estado. Sirve
 * para dar soporte: encontrar a una persona y, si hace falta, desactivar o reactivar al personal de una empresa.
 * Cada desactivación o reactivación queda en el registro de acciones.
 */
#[Title('Usuarios')]
class Listado extends Component
{
    use SeActualizaSola;
    use WithListado;

    #[Url(except: '')]
    public string $rol = '';

    #[Url(except: '')]
    public string $situacion = '';

    public bool $mostrarConfirmacion = false;

    #[Locked]
    public ?int $usuarioSeleccionado = null;

    /** Lo que se confirmó al abrir: desactivar (true) o reactivar (false). */
    #[Locked]
    public bool $desactivarSeleccionado = true;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    /** @return list<string> */
    protected function camposBusqueda(): array
    {
        return ['name', 'email', 'empresa.nombre'];
    }

    /** @return array<string, string> */
    protected function opcionesOrden(): array
    {
        return ['nombre' => 'Nombre', 'reciente' => 'Alta más reciente'];
    }

    protected function columnaDeOrden(string $clave): string
    {
        return $clave === 'reciente' ? 'users.created_at' : 'users.name';
    }

    protected function porPagina(): int
    {
        return 15;
    }

    public function updatedRol(): void
    {
        $this->resetPage();
    }

    public function updatedSituacion(): void
    {
        $this->resetPage();
    }

    public function hayFiltros(): bool
    {
        return $this->busqueda !== '' || $this->rol !== '' || $this->situacion !== '';
    }

    public function confirmar(int $id): void
    {
        $usuario = $this->usuario($id);
        $this->authorize('gestionarDesdeLaPlataforma', $usuario);

        $this->resetValidation();
        $this->usuarioSeleccionado = $usuario->id;
        $this->desactivarSeleccionado = $usuario->estaActivo();
        $this->mostrarConfirmacion = true;
    }

    public function cambiarEstado(CambiarEstadoUsuario $cambiar, RegistroDeAuditoria $auditoria): void
    {
        $usuario = $this->usuario((int) $this->usuarioSeleccionado);
        $this->authorize('gestionarDesdeLaPlataforma', $usuario);

        /** @var User $admin */
        $admin = auth()->user();

        try {
            if ($this->desactivarSeleccionado) {
                $cambiar->desactivar($usuario);
                $auditoria->anotar($admin, RegistroDeAuditoria::USUARIO_DESACTIVADO, "Se desactivó la cuenta de {$usuario->name} ({$usuario->email}).", $usuario->empresa_id);
            } else {
                $cambiar->reactivar($usuario);
                $auditoria->anotar($admin, RegistroDeAuditoria::USUARIO_REACTIVADO, "Se reactivó la cuenta de {$usuario->name} ({$usuario->email}).", $usuario->empresa_id);
            }
        } catch (OperacionNoPermitida $e) {
            $this->addError('usuario', $e->getMessage());
        }

        $this->mostrarConfirmacion = false;
    }

    /** Usuarios que se registran, aceptan una invitación o se activan y desactivan. */
    protected function partesDeLaFirma(): array
    {
        $contexto = app(TenantContext::class);
        $usuarios = $contexto->esGlobal() ? User::query() : User::query()->where('empresa_id', $contexto->id());

        return [$this->parteDe($usuarios), $this->parteDe(Invitacion::query())];
    }

    public function render(): View
    {
        $consulta = User::query()->with(['roles', 'empresa:id,nombre'])
            ->when($this->rol !== '', fn ($q) => $q->role($this->rol))
            ->when($this->situacion === 'activos', fn ($q) => $q->whereNull('desactivado_at'))
            ->when($this->situacion === 'desactivados', fn ($q) => $q->whereNotNull('desactivado_at'));

        return view('livewire.usuarios.listado', [
            'filas' => $this->listar($consulta),
            'roles' => collect(Rol::cases())->mapWithKeys(fn (Rol $r) => [$r->value => $r->etiqueta()])->all(),
            'seleccionado' => $this->mostrarConfirmacion && $this->usuarioSeleccionado ? User::find($this->usuarioSeleccionado) : null,
        ]);
    }

    private function usuario(int $id): User
    {
        return User::findOrFail($id);
    }
}

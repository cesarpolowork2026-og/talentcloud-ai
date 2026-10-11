<?php

namespace App\Livewire\MiEmpresa;

use App\DTO\DatosEmpresaData;
use App\DTO\InvitacionData;
use App\Enums\Periodicidad;
use App\Enums\PlanEmpresa;
use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Invitacion;
use App\Models\User;
use App\Pagos\CobroEnLinea;
use App\Repositories\Contracts\EmpresaRepository;
use App\Repositories\Contracts\EquipoRepository;
use App\Services\Empresas\ActualizarEmpresa;
use App\Services\Empresas\LimitesDelPlan;
use App\Services\Empresas\SolicitarCambioDePlan;
use App\Services\Equipo\CambiarEstadoUsuario;
use App\Services\Equipo\InvitacionNoValida;
use App\Services\Equipo\InvitarUsuario;
use App\Services\Pagos\ContratarPlan;
use App\Services\Pagos\GestionarSuscripcion;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Pantalla "Mi empresa" de la cuenta de empresa (prototipo: isMiEmpresa). */
#[Title('Mi empresa')]
class Panel extends Component
{
    // Invitar usuario.
    public bool $mostrarInvitacion = false;

    public string $invitadoNombre = '';

    public string $invitadoEmail = '';

    public string $invitadoRol = 'reclutador';

    // Datos de la empresa.
    public bool $mostrarDatos = false;

    public string $nombre = '';

    public string $sector = '';

    public string $sitioWeb = '';

    public string $descripcion = '';

    // Desactivar / reactivar usuario.
    public bool $mostrarConfirmacionUsuario = false;

    #[Locked]
    public ?int $usuarioSeleccionado = null;

    /** Lo que se confirmó al abrir el diálogo: desactivar (true) o reactivar (false). */
    #[Locked]
    public bool $desactivarSeleccionado = true;

    // Planes: solicitar (sin pasarela o Enterprise) o pagar en línea.
    public bool $mostrarPlanes = false;

    /** Vuelta de la página de pago: "confirmando" (pagó; falta el aviso de la pasarela) o "cancelado". */
    #[Locked]
    public ?string $pago = null;

    // Revocar invitación.
    public bool $mostrarConfirmacionInvitacion = false;

    #[Locked]
    public ?int $invitacionSeleccionada = null;

    public function mount(): void
    {
        $this->authorize('view', $this->empresa());

        // Accesos directos desde el onboarding: ?abrir=datos | ?abrir=invitar
        match (request()->query('abrir')) {
            'datos' => $this->abrirDatos(),
            'invitar' => $this->abrirInvitacion(),
            'planes' => $this->abrirPlanes(),
            default => null,
        };

        $pago = request()->query('pago');
        $this->pago = in_array($pago, ['confirmando', 'cancelado'], true) ? $pago : null;
    }

    public function abrirInvitacion(): void
    {
        $this->authorize('invitar', $this->empresa());

        $this->resetValidation();
        $this->reset('invitadoNombre', 'invitadoEmail', 'invitadoRol');
        $this->mostrarInvitacion = true;
    }

    public function invitar(InvitarUsuario $invitar): void
    {
        $empresa = $this->empresa();
        $this->authorize('invitar', $empresa);

        $datos = $this->validate([
            'invitadoNombre' => ['required', 'string', 'max:255'],
            'invitadoEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'invitadoRol' => ['required', Rule::in(array_map(fn (Rol $r) => $r->value, Rol::internosDeEmpresa()))],
        ], attributes: [
            'invitadoNombre' => 'nombre',
            'invitadoEmail' => 'correo',
            'invitadoRol' => 'rol',
        ]);

        /** @var User $actor */
        $actor = auth()->user();

        try {
            $invitar->ejecutar($empresa, new InvitacionData($datos['invitadoNombre'], $datos['invitadoEmail'], Rol::from($datos['invitadoRol'])), $actor);
        } catch (InvitacionNoValida $e) {
            $this->addError('invitadoEmail', $e->getMessage());

            return;
        }

        $this->mostrarInvitacion = false;
        $this->dispatch('invitacion-enviada');
    }

    public function confirmarRevocacion(int $id): void
    {
        $this->authorize('invitar', $this->empresa());

        $this->invitacionSeleccionada = $this->invitacionPendiente($id)->id;
        $this->mostrarConfirmacionInvitacion = true;
    }

    public function revocarInvitacion(): void
    {
        $this->authorize('invitar', $this->empresa());

        $this->invitacionPendiente((int) $this->invitacionSeleccionada)->delete();

        $this->mostrarConfirmacionInvitacion = false;
    }

    public function confirmarCambioUsuario(int $id): void
    {
        $this->authorize('gestionar', $usuario = $this->usuario($id));

        $this->resetValidation();
        $this->usuarioSeleccionado = $usuario->id;
        $this->desactivarSeleccionado = $usuario->estaActivo();
        $this->mostrarConfirmacionUsuario = true;
    }

    public function cambiarEstadoUsuario(CambiarEstadoUsuario $cambiar): void
    {
        $usuario = $this->usuario((int) $this->usuarioSeleccionado);
        $this->authorize('gestionar', $usuario);

        $this->mostrarConfirmacionUsuario = false;

        try {
            $this->desactivarSeleccionado ? $cambiar->desactivar($usuario) : $cambiar->reactivar($usuario);
        } catch (OperacionNoPermitida $e) {
            $this->addError('usuario', $e->getMessage());
        }
    }

    public function abrirPlanes(): void
    {
        $this->authorize('solicitarPlan', $this->empresa());

        $this->resetValidation('plan');
        $this->mostrarPlanes = true;
    }

    public function solicitarPlan(string $plan, SolicitarCambioDePlan $solicitar): void
    {
        $empresa = $this->empresa();
        $this->authorize('solicitarPlan', $empresa);

        $elegido = PlanEmpresa::tryFrom($plan);
        abort_if($elegido === null, 404);

        /** @var User $actor */
        $actor = auth()->user();

        try {
            $solicitar->ejecutar($empresa, $elegido, $actor);
        } catch (OperacionNoPermitida $e) {
            $this->addError('plan', $e->getMessage());

            return;
        }

        $this->resetValidation('plan');
        $this->mostrarPlanes = false;
        $this->dispatch('plan-solicitado');
    }

    /** Lleva a la página de pago de la pasarela; el plan cambia cuando la pasarela confirma el pago. */
    public function contratarPlan(string $plan, string $periodicidad, ContratarPlan $contratar): void
    {
        $empresa = $this->empresa();
        $this->authorize('pagarPlan', $empresa);
        abort_unless(CobroEnLinea::habilitado(), 404);

        $elegido = PlanEmpresa::tryFrom($plan);
        $cada = Periodicidad::tryFrom($periodicidad);
        abort_if($elegido === null || $cada === null, 404);

        /** @var User $actor */
        $actor = auth()->user();

        try {
            $url = $contratar->ejecutar($empresa, $elegido, $cada, $actor);
        } catch (OperacionNoPermitida $e) {
            $this->addError('plan', $e->getMessage());

            return;
        }

        $this->redirect($url);
    }

    /** Portal de la pasarela: tarjeta, cambio de plan, facturas y cancelación. */
    public function gestionarSuscripcion(GestionarSuscripcion $gestionar): void
    {
        $empresa = $this->empresa();
        $this->authorize('pagarPlan', $empresa);
        abort_unless(CobroEnLinea::habilitado(), 404);

        try {
            $url = $gestionar->url($empresa);
        } catch (OperacionNoPermitida $e) {
            $this->addError('plan', $e->getMessage());

            return;
        }

        $this->redirect($url);
    }

    public function abrirDatos(): void
    {
        $empresa = $this->empresa();
        $this->authorize('update', $empresa);

        $this->resetValidation();
        $this->nombre = $empresa->nombre;
        $this->sector = (string) $empresa->sector;
        $this->sitioWeb = (string) $empresa->sitio_web;
        $this->descripcion = (string) $empresa->descripcion;
        $this->mostrarDatos = true;
    }

    public function guardarDatos(ActualizarEmpresa $actualizar): void
    {
        $empresa = $this->empresa();
        $this->authorize('update', $empresa);

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('empresas', 'nombre')->ignore($empresa->id)],
            'sector' => ['nullable', 'string', 'max:120'],
            'sitioWeb' => ['nullable', 'url', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['sitioWeb' => 'sitio web']);

        $actualizar->ejecutar($empresa, new DatosEmpresaData(
            nombre: $datos['nombre'],
            sector: $datos['sector'] ?: null,
            sitioWeb: $datos['sitioWeb'] ?: null,
            descripcion: $datos['descripcion'] ?: null,
        ));

        $this->mostrarDatos = false;
    }

    public function render(EquipoRepository $equipo, EmpresaRepository $empresas, LimitesDelPlan $limites): View
    {
        $empresa = $empresas->conConteos($this->empresa());

        return view('livewire.mi-empresa.panel', [
            'empresa' => $empresa,
            'uso' => $limites->uso($empresa),
            'cobroEnLinea' => CobroEnLinea::habilitado(),
            'usuarios' => $equipo->usuariosDe($empresa),
            'invitaciones' => $equipo->invitacionesPendientesDe($empresa),
            'seleccionado' => $this->usuarioSeleccionado ? User::find($this->usuarioSeleccionado) : null,
            'descripcionesDeRol' => array_combine(
                array_map(fn (Rol $r) => $r->etiquetaEnEmpresa(), Rol::internosDeEmpresa()),
                array_map(fn (Rol $r) => $r->descripcionEnEmpresa(), Rol::internosDeEmpresa()),
            ),
            'roles' => array_combine(
                array_map(fn (Rol $r) => $r->value, Rol::internosDeEmpresa()),
                array_map(fn (Rol $r) => $r->etiquetaEnEmpresa(), Rol::internosDeEmpresa()),
            ),
        ]);
    }

    private function empresa(): Empresa
    {
        /** @var User $user */
        $user = auth()->user();

        abort_if($user->empresa === null, 403);

        return $user->empresa;
    }

    /** Con el filtro por empresa, una invitación de otra empresa no se encuentra. */
    private function invitacionPendiente(int $id): Invitacion
    {
        $invitacion = Invitacion::query()->whereKey($id)->whereNull('aceptada_at')->first();

        abort_if($invitacion === null, 404);

        return $invitacion;
    }

    /** Solo usuarios de la propia empresa: los de otra dan 404. */
    private function usuario(int $id): User
    {
        $usuario = User::query()->deEmpresa($this->empresa()->id)->find($id);

        abort_if($usuario === null, 404);

        return $usuario;
    }
}

<?php

namespace App\Livewire\Auth;

use App\Repositories\Contracts\EquipoRepository;
use App\Services\Equipo\AceptarInvitacion as AceptarInvitacionService;
use App\Services\Equipo\EquipoCompleto;
use App\Services\Equipo\InvitacionNoValida;
use App\Support\Navegacion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Enlace del correo de invitación: la persona crea su cuenta en la empresa. */
#[Layout('layouts.guest')]
#[Title('Aceptar invitación')]
class AceptarInvitacion extends Component
{
    #[Locked]
    public string $token = '';

    #[Locked]
    public bool $valida = false;

    #[Locked]
    public string $empresa = '';

    #[Locked]
    public string $email = '';

    #[Locked]
    public string $rol = '';

    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token, EquipoRepository $equipo): void
    {
        $this->token = $token;

        $invitacion = $equipo->invitacionVigentePorToken($token);

        if ($invitacion === null || $invitacion->empresa === null) {
            return;
        }

        $this->valida = true;
        $this->empresa = $invitacion->empresa->nombre;
        $this->email = $invitacion->email;
        $this->rol = $invitacion->rol->etiquetaEnEmpresa();
        $this->name = (string) $invitacion->nombre;
    }

    public function aceptar(AceptarInvitacionService $aceptar): void
    {
        $datos = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        try {
            $user = $aceptar->ejecutar($this->token, $datos['name'], $datos['password']);
        } catch (EquipoCompleto $e) {
            // La invitación sigue vigente: el formulario se queda y se explica el motivo.
            $this->addError('name', $e->getMessage());

            return;
        } catch (InvitacionNoValida $e) {
            $this->valida = false;
            $this->addError('name', $e->getMessage());

            return;
        }

        Auth::login($user);
        Session::regenerate();

        $this->redirect(Navegacion::rutaInicio($user), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.aceptar-invitacion');
    }
}

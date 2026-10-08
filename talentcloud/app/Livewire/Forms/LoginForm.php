<?php

namespace App\Livewire\Forms;

use App\Enums\Rol;
use App\Models\User;
use App\Tenancy\VerificarAcceso;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class LoginForm extends Form
{
    /** Intentos fallidos por minuto desde una misma IP, sea cual sea el correo. */
    public const INTENTOS_POR_IP = 20;

    public string $email = '';

    public string $password = '';

    /** "Ingresar como" del prototipo; por defecto Reclutador, como en el diseño. */
    public string $rol = 'reclutador';

    public bool $remember = false;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'rol' => ['required', Rule::enum(Rol::class)],
            'remember' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'rol' => 'rol',
        ];
    }

    /**
     * Autentica las credenciales y comprueba que la cuenta tenga el rol elegido.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only(['email', 'password']), $this->remember)) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->claveDeIp());

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        $rol = Rol::from($this->rol);

        /** @var User $user */
        $user = Auth::user();

        if (! $user->tieneRol($rol)) {
            Auth::guard('web')->logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.rol' => 'Esta cuenta no tiene acceso como '.$rol->etiqueta().'.',
            ]);
        }

        // Usuario desactivado o empresa suspendida: no se abre la sesión.
        if (($motivo = VerificarAcceso::motivoDeBloqueo($user)) !== null) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'form.email' => $motivo,
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        // 5 intentos por correo e IP, y 20 por IP: sin el segundo, desde una IP se podrían probar muchos correos.
        $clave = match (true) {
            RateLimiter::tooManyAttempts($this->throttleKey(), 5) => $this->throttleKey(),
            RateLimiter::tooManyAttempts($this->claveDeIp(), self::INTENTOS_POR_IP) => $this->claveDeIp(),
            default => null,
        };

        if ($clave === null) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($clave);

        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function claveDeIp(): string
    {
        return 'login-ip|'.request()->ip();
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}

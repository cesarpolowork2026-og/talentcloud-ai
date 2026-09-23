<?php

namespace App\Models;

use App\Enums\Rol;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'notificaciones_email' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'notificaciones_email',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notificaciones_email' => 'boolean',
        ];
    }

    /** Rol principal del usuario. Cada cuenta tiene un único rol de la plataforma. */
    public function rol(): ?Rol
    {
        foreach (Rol::cases() as $rol) {
            if ($this->hasRole($rol->value)) {
                return $rol;
            }
        }

        return null;
    }

    public function tieneRol(Rol $rol): bool
    {
        return $this->hasRole($rol->value);
    }
}

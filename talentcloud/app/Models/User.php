<?php

namespace App\Models;

use App\Enums\Rol;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * Cuenta de la plataforma. No lleva BelongsToEmpresa: un Global Scope que use
 * el usuario autenticado provocaría recursión al resolverlo. Los usuarios de
 * una empresa se filtran con el scope explícito deEmpresa() y con Policies.
 *
 * empresa_id no es asignable en masa: lo fijan los servicios de registro e invitación.
 *
 * @property int|null $empresa_id
 * @property Carbon|null $desactivado_at
 * @property-read Empresa|null $empresa
 */
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
            'desactivado_at' => 'datetime',
        ];
    }

    public function estaActivo(): bool
    {
        return $this->desactivado_at === null;
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

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Perfil de candidato del postulante.
     *
     * @return HasOne<Candidato, $this>
     */
    public function candidato(): HasOne
    {
        return $this->hasOne(Candidato::class);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeDeEmpresa(Builder $query, int $empresaId): void
    {
        $query->where('empresa_id', $empresaId);
    }
}

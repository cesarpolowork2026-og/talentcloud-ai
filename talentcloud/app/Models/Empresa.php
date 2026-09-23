<?php

namespace App\Models;

use App\Enums\EstadoEmpresa;
use App\Enums\PlanEmpresa;
use Database\Factories\EmpresaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Empresa cliente de la plataforma (el tenant).
 *
 * @property int $id
 * @property string $nombre
 * @property PlanEmpresa $plan
 * @property EstadoEmpresa $estado
 * @property array<string, bool>|null $onboarding
 */
class Empresa extends Model
{
    /** @use HasFactory<EmpresaFactory> */
    use HasFactory;

    /** Pasos del checklist de bienvenida del prototipo. */
    public const PASOS_ONBOARDING = ['perfil', 'vacante', 'invitar'];

    protected $fillable = [
        'nombre',
        'sector',
        'sitio_web',
        'descripcion',
        'plan',
        'estado',
        'onboarding',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'plan' => 'starter',
        'estado' => 'activa',
    ];

    protected function casts(): array
    {
        return [
            'plan' => PlanEmpresa::class,
            'estado' => EstadoEmpresa::class,
            'onboarding' => 'array',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Vacante, $this>
     */
    public function vacantes(): HasMany
    {
        return $this->hasMany(Vacante::class);
    }

    /**
     * @return HasMany<Invitacion, $this>
     */
    public function invitaciones(): HasMany
    {
        return $this->hasMany(Invitacion::class);
    }

    public function estaActiva(): bool
    {
        return $this->estado === EstadoEmpresa::Activa;
    }
}

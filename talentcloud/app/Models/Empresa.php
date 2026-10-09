<?php

namespace App\Models;

use App\Enums\EstadoEmpresa;
use App\Enums\PlanEmpresa;
use Database\Factories\EmpresaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Empresa cliente de la plataforma (el tenant).
 *
 * @property int $id
 * @property string $nombre
 * @property PlanEmpresa $plan plan contratado (durante la prueba, Gratis)
 * @property Carbon|null $prueba_hasta
 * @property PlanEmpresa|null $plan_solicitado
 * @property Carbon|null $plan_solicitado_at
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
        'plan' => 'gratis',
        'estado' => 'activa',
    ];

    protected function casts(): array
    {
        return [
            'plan' => PlanEmpresa::class,
            'prueba_hasta' => 'datetime',
            'plan_solicitado' => PlanEmpresa::class,
            'plan_solicitado_at' => 'datetime',
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

    /**
     * @return HasMany<Postulacion, $this>
     */
    public function postulaciones(): HasMany
    {
        return $this->hasMany(Postulacion::class);
    }

    /**
     * Plan cuyos límites rigen hoy: el de prueba mientras dure la prueba y, después,
     * el contratado. La prueba vence sola, sin tareas programadas.
     */
    public function planVigente(): PlanEmpresa
    {
        return $this->enPrueba() ? PlanEmpresa::PLAN_DE_PRUEBA : $this->plan;
    }

    public function enPrueba(): bool
    {
        return $this->plan === PlanEmpresa::Gratis && $this->prueba_hasta?->isFuture() === true;
    }

    /** Días que le quedan a la prueba, contando el de hoy (0 si no está en prueba). */
    public function diasDePrueba(): int
    {
        return $this->enPrueba() ? max(1, (int) ceil(now()->diffInDays($this->prueba_hasta))) : 0;
    }

    /** "Pro (prueba)" durante la prueba; si no, el plan contratado. */
    public function etiquetaDelPlan(): string
    {
        return $this->enPrueba() ? PlanEmpresa::PLAN_DE_PRUEBA->etiqueta().' (prueba)' : $this->plan->etiqueta();
    }

    public function estaActiva(): bool
    {
        return $this->estado === EstadoEmpresa::Activa;
    }
}

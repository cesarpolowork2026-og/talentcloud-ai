<?php

namespace App\Models;

use App\Enums\EstadoEmpresa;
use App\Enums\EstadoSuscripcion;
use App\Enums\Periodicidad;
use App\Enums\PlanEmpresa;
use App\Support\BolsaPublica;
use Database\Factories\EmpresaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Empresa cliente de la plataforma (el tenant).
 *
 * @property int $id
 * @property string $nombre
 * @property PlanEmpresa $plan plan contratado (durante la prueba, Gratis)
 * @property Carbon|null $prueba_hasta
 * @property Carbon|null $plan_vence_at vencimiento de un plan asignado a mano (null = no vence)
 * @property Carbon|null $plan_aviso_pronto_para vencimiento al que se refería el aviso «vence pronto» ya enviado
 * @property Carbon|null $plan_aviso_vencido_para vencimiento al que se refería el aviso «venció» ya enviado
 * @property PlanEmpresa|null $plan_solicitado
 * @property Carbon|null $plan_solicitado_at
 * @property EstadoEmpresa $estado
 * @property string|null $pasarela_cliente_id cliente de la pasarela de pago
 * @property string|null $pasarela_suscripcion_id última suscripción en línea
 * @property EstadoSuscripcion|null $suscripcion_estado
 * @property Periodicidad|null $suscripcion_periodicidad
 * @property Carbon|null $suscripcion_renueva_at fin del periodo pagado
 * @property bool $suscripcion_cancela_al_final
 * @property array<string, bool>|null $onboarding
 */
class Empresa extends Model
{
    /** @use HasFactory<EmpresaFactory> */
    use HasFactory;

    /** Pasos del checklist de bienvenida del prototipo. */
    public const PASOS_ONBOARDING = ['perfil', 'vacante', 'invitar'];

    /** Con cuántos días de anticipación se avisa que un plan asignado a mano está por vencer. */
    public const DIAS_DE_AVISO = 15;

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

    protected static function booted(): void
    {
        static::saved(function (Empresa $empresa): void {
            Cache::forget(self::claveDeActiva($empresa->id));
            BolsaPublica::olvidar();
        });
        static::deleted(function (Empresa $empresa): void {
            Cache::forget(self::claveDeActiva($empresa->id));
            BolsaPublica::olvidar();
        });
    }

    /**
     * ¿La empresa está activa? La pide `VerificarAcceso` en cada petición y en cada acción de Livewire:
     * se recuerda 60 s y se olvida al guardar la empresa, así que suspenderla sigue surtiendo efecto
     * en la siguiente petición. Una empresa que no existe no bloquea (como antes).
     */
    public static function estaActivaConCache(int $id): bool
    {
        return Cache::remember(self::claveDeActiva($id), 60, fn () => self::query()->whereKey($id)->first(['id', 'estado'])?->estaActiva() ?? true);
    }

    private static function claveDeActiva(int $id): string
    {
        return "empresa:{$id}:activa";
    }

    protected function casts(): array
    {
        return [
            'plan' => PlanEmpresa::class,
            'prueba_hasta' => 'datetime',
            'plan_vence_at' => 'datetime',
            'plan_aviso_pronto_para' => 'datetime',
            'plan_aviso_vencido_para' => 'datetime',
            'plan_solicitado' => PlanEmpresa::class,
            'plan_solicitado_at' => 'datetime',
            'estado' => EstadoEmpresa::class,
            'onboarding' => 'array',
            'suscripcion_estado' => EstadoSuscripcion::class,
            'suscripcion_periodicidad' => Periodicidad::class,
            'suscripcion_renueva_at' => 'datetime',
            'suscripcion_cancela_al_final' => 'boolean',
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
     * el contratado. La prueba y el plan asignado a mano vencen solos, sin tareas programadas.
     */
    public function planVigente(): PlanEmpresa
    {
        return $this->enPrueba() ? PlanEmpresa::PLAN_DE_PRUEBA : $this->planContratado();
    }

    /** El plan contratado que sigue en pie: el guardado, o Gratis si su vencimiento ya pasó. */
    public function planContratado(): PlanEmpresa
    {
        return $this->planVencido() ? PlanEmpresa::Gratis : $this->plan;
    }

    /**
     * ¿El plan asignado a mano ya venció? Un plan sin fecha no vence, y uno pagado con tarjeta lo gobierna
     * la pasarela (suscripcion_renueva_at), no esta fecha.
     */
    public function planVencido(): bool
    {
        return $this->tieneVencimientoPropio() && $this->plan_vence_at->isPast();
    }

    /** ¿Tiene un plan de pago con fecha de vencimiento por venir (o ya pasada)? Excluye suscripciones y la prueba. */
    public function tieneVencimientoPropio(): bool
    {
        return $this->plan !== PlanEmpresa::Gratis
            && $this->plan_vence_at !== null
            && ! $this->tieneSuscripcionVigente();
    }

    /** ¿El plan vence en los próximos DIAS_DE_AVISO días (sin haber vencido aún)? */
    public function planVencePronto(): bool
    {
        return $this->tieneVencimientoPropio()
            && ! $this->planVencido()
            && $this->plan_vence_at->lte(now()->addDays(self::DIAS_DE_AVISO));
    }

    /** Días que le quedan al plan, contando el de hoy (0 si no tiene vencimiento o ya venció). */
    public function diasParaVencer(): int
    {
        return $this->tieneVencimientoPropio() && ! $this->planVencido()
            ? max(1, (int) ceil(now()->diffInDays($this->plan_vence_at)))
            : 0;
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

    /** "Pro (prueba)" durante la prueba; si no, el plan contratado que sigue en pie. */
    public function etiquetaDelPlan(): string
    {
        return $this->enPrueba() ? PlanEmpresa::PLAN_DE_PRUEBA->etiqueta().' (prueba)' : $this->planContratado()->etiqueta();
    }

    /**
     * Fija el plan contratado: termina la prueba y atiende la solicitud de plan, si
     * la había. Lo usan el admin SaaS (CambiarPlanEmpresa) y el cobro en línea.
     * `$venceAt` solo tiene sentido en un plan de pago asignado a mano; Gratis y los pagados con tarjeta no lo llevan.
     */
    public function contratar(PlanEmpresa $plan, ?Carbon $venceAt = null): void
    {
        $this->plan = $plan;
        $this->plan_vence_at = $plan === PlanEmpresa::Gratis ? null : $venceAt;
        $this->prueba_hasta = null;
        $this->plan_solicitado = null;
        $this->plan_solicitado_at = null;
    }

    /** Paga su plan en línea (aunque un cobro esté en reintento): el plan lo cambia la pasarela. */
    public function tieneSuscripcionVigente(): bool
    {
        return $this->suscripcion_estado?->esVigente() === true;
    }

    public function estaActiva(): bool
    {
        return $this->estado === EstadoEmpresa::Activa;
    }
}

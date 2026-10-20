<?php

namespace App\Models;

use App\Enums\EstadoDeMiPostulacion;
use App\Enums\EtapaPostulacion;
use App\Enums\OrigenPostulacion;
use App\Observers\PostulacionObserver;
use App\Tenancy\BelongsToEmpresa;
use Database\Factories\PostulacionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Postulación de un candidato a una vacante: su etapa en el Kanban. La decisión
 * de avanzarla o descartarla es siempre del reclutador.
 *
 * @property int $id
 * @property int $empresa_id
 * @property int $vacante_id
 * @property int $candidato_id
 * @property int|null $cv_id
 * @property EtapaPostulacion $etapa
 * @property int $orden
 * @property Carbon|null $etapa_cambiada_at
 * @property OrigenPostulacion $origen
 * @property Carbon|null $retirada_at La retiró el propio postulante (no asignable en masa).
 * @property Carbon $created_at
 * @property EstadoDeMiPostulacion|null $estado_avisado Último estado comunicado al postulante (no asignable en masa).
 */
#[ObservedBy(PostulacionObserver::class)]
class Postulacion extends Model
{
    /** @use HasFactory<PostulacionFactory> */
    use BelongsToEmpresa, HasFactory;

    protected $table = 'postulaciones';

    protected $fillable = [
        'vacante_id',
        'candidato_id',
        'cv_id',
        'etapa',
        'orden',
        'etapa_cambiada_at',
        'origen',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'etapa' => 'postulado',
        'orden' => 0,
        'origen' => 'equipo',
    ];

    protected function casts(): array
    {
        return [
            'etapa' => EtapaPostulacion::class,
            'orden' => 'integer',
            'etapa_cambiada_at' => 'datetime',
            'origen' => OrigenPostulacion::class,
            'retirada_at' => 'datetime',
            'estado_avisado' => EstadoDeMiPostulacion::class,
        ];
    }

    /**
     * @return BelongsTo<Vacante, $this>
     */
    public function vacante(): BelongsTo
    {
        return $this->belongsTo(Vacante::class);
    }

    /**
     * @return BelongsTo<Candidato, $this>
     */
    public function candidato(): BelongsTo
    {
        return $this->belongsTo(Candidato::class);
    }

    /**
     * Notas internas del equipo, de la más reciente a la más antigua.
     *
     * @return HasMany<Nota, $this>
     */
    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class)->latest()->latest('id');
    }

    /**
     * Historial de etapas, de la más antigua a la más reciente.
     *
     * @return HasMany<PostulacionEtapa, $this>
     */
    public function etapas(): HasMany
    {
        return $this->hasMany(PostulacionEtapa::class)->orderBy('desde')->orderBy('id');
    }

    /**
     * @return BelongsTo<Cv, $this>
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(Cv::class);
    }

    /**
     * @return HasMany<Entrevista, $this>
     */
    public function entrevistas(): HasMany
    {
        return $this->hasMany(Entrevista::class);
    }

    /**
     * @return HasMany<Evaluacion, $this>
     */
    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class);
    }

    /** La retiró el propio postulante (está en Descartado, pero no la descartó la empresa). */
    /**
     * Por nombre del candidato o título de la vacante (sin importar tildes ni mayúsculas): para los
     * buscadores, que no pueden listar todas las postulaciones de una empresa.
     *
     * @param  Builder<static>  $consulta
     */
    public function scopeBuscar(Builder $consulta, string $texto): void
    {
        $patron = '%'.addcslashes(trim($texto), '%_\\').'%';

        $consulta->where(fn (Builder $q) => $q
            ->whereHas('candidato', fn (Builder $c) => $c->whereRaw('unaccent(candidatos.nombre) ilike unaccent(?)', [$patron]))
            ->orWhereHas('vacante', fn (Builder $v) => $v->whereRaw('unaccent(vacantes.titulo) ilike unaccent(?)', [$patron])));
    }

    public function estaRetirada(): bool
    {
        return $this->retirada_at !== null;
    }

    /** Etapa para el personal de selección: "Retirada" si la retiró el candidato (está en Descartado). */
    public function etapaVisible(): string
    {
        return $this->estaRetirada() ? 'Retirada' : $this->etapa->etiqueta();
    }
}

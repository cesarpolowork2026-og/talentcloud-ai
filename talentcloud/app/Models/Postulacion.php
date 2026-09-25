<?php

namespace App\Models;

use App\Enums\EstadoDeMiPostulacion;
use App\Enums\EtapaPostulacion;
use App\Enums\MotorMatching;
use App\Enums\OrigenPostulacion;
use App\Observers\PostulacionObserver;
use App\Tenancy\BelongsToEmpresa;
use Database\Factories\PostulacionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Postulación de un candidato a una vacante: su etapa en el Kanban y el
 * resultado del matching (sugerencia de la IA; la decisión es del reclutador).
 *
 * @property int $id
 * @property int $empresa_id
 * @property int $vacante_id
 * @property int $candidato_id
 * @property int|null $cv_id
 * @property EtapaPostulacion $etapa
 * @property int $orden
 * @property string|null $match_score
 * @property array<string, mixed>|null $match_detalle
 * @property MotorMatching|null $match_motor
 * @property Carbon|null $etapa_cambiada_at
 * @property OrigenPostulacion $origen
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
        'match_score',
        'match_detalle',
        'match_motor',
        'match_calculado_at',
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
            'estado_avisado' => EstadoDeMiPostulacion::class,
            'match_score' => 'decimal:2',
            'match_detalle' => 'array',
            'match_motor' => MotorMatching::class,
            'match_calculado_at' => 'datetime',
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

    /** Porcentaje de coincidencia redondeado ("88% match"), o null si no se calculó. */
    public function matchPorcentaje(): ?int
    {
        return $this->match_score === null ? null : (int) round((float) $this->match_score);
    }
}

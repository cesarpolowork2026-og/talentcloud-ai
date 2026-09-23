<?php

namespace App\Models;

use App\Enums\EstadoEvaluacion;
use App\Tenancy\BelongsToEmpresa;
use Database\Factories\EvaluacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evaluación de un candidato por criterios; el puntaje (0–10) es su promedio.
 *
 * @property int $id
 * @property int $postulacion_id
 * @property int $evaluador_id
 * @property list<array{nombre: string, puntaje: int|float}> $criterios
 * @property string|null $puntaje
 * @property EstadoEvaluacion $estado
 */
class Evaluacion extends Model
{
    /** @use HasFactory<EvaluacionFactory> */
    use BelongsToEmpresa, HasFactory;

    protected $table = 'evaluaciones';

    protected $fillable = [
        'postulacion_id',
        'evaluador_id',
        'criterios',
        'puntaje',
        'comentario',
        'estado',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'criterios' => '[]',
        'estado' => 'pendiente',
    ];

    protected function casts(): array
    {
        return [
            'criterios' => 'array',
            'puntaje' => 'decimal:1',
            'estado' => EstadoEvaluacion::class,
        ];
    }

    /**
     * @return BelongsTo<Postulacion, $this>
     */
    public function postulacion(): BelongsTo
    {
        return $this->belongsTo(Postulacion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }

    /**
     * Promedio de los puntajes de los criterios, con un decimal.
     *
     * @param  list<array{nombre: string, puntaje: int|float}>  $criterios
     */
    public static function promediar(array $criterios): ?float
    {
        if ($criterios === []) {
            return null;
        }

        return round(array_sum(array_column($criterios, 'puntaje')) / count($criterios), 1);
    }
}

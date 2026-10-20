<?php

namespace App\Models;

use App\Enums\TipoRequisito;
use App\Support\BolsaPublica;
use App\Tenancy\BelongsToEmpresa;
use Database\Factories\RequisitoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Requisito de una vacante: una competencia del catálogo, obligatoria o
 * deseable, con su importancia (de 1 a 5) y los años mínimos de experiencia.
 *
 * @property int $id
 * @property int $vacante_id
 * @property int $competencia_id
 * @property TipoRequisito $tipo
 * @property int $peso Importancia del requisito, de 1 (baja) a 5 (alta).
 * @property int|null $anios_minimos
 * @property string|null $descripcion
 */
class Requisito extends Model
{
    /** @use HasFactory<RequisitoFactory> */
    use BelongsToEmpresa, HasFactory;

    protected $table = 'requisitos';

    protected $fillable = [
        'vacante_id',
        'competencia_id',
        'descripcion',
        'tipo',
        'peso',
        'anios_minimos',
        'orden',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'tipo' => 'obligatorio',
        'peso' => 3,
    ];

    protected static function booted(): void
    {
        static::saved(fn () => BolsaPublica::olvidar());
        static::deleted(fn () => BolsaPublica::olvidar());
    }

    protected function casts(): array
    {
        return [
            'tipo' => TipoRequisito::class,
            'peso' => 'integer',
            'anios_minimos' => 'integer',
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
     * @return BelongsTo<Competencia, $this>
     */
    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class);
    }
}

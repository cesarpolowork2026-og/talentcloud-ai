<?php

namespace App\Models;

use App\Enums\TipoRequisito;
use App\Tenancy\BelongsToEmpresa;
use Database\Factories\RequisitoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Requisito de una vacante: una competencia del catálogo, obligatoria o
 * deseable, con su peso en el score global.
 *
 * @property int $id
 * @property int $vacante_id
 * @property int $competencia_id
 * @property TipoRequisito $tipo
 * @property int $peso
 * @property int|null $anios_minimos
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

    /** El embedding solo lo usan el servicio de IA y las consultas de pgvector. */
    protected $hidden = ['embedding'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'tipo' => 'obligatorio',
        'peso' => 3,
    ];

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

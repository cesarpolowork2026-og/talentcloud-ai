<?php

namespace App\Models;

use App\Enums\EstadoEntrevista;
use App\Enums\ModalidadEntrevista;
use App\Tenancy\BelongsToEmpresa;
use Database\Factories\EntrevistaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $postulacion_id
 * @property int $entrevistador_id
 * @property Carbon $inicia_at
 * @property int $duracion_min
 * @property ModalidadEntrevista $modalidad
 * @property EstadoEntrevista $estado
 */
class Entrevista extends Model
{
    /** @use HasFactory<EntrevistaFactory> */
    use BelongsToEmpresa, HasFactory;

    protected $table = 'entrevistas';

    protected $fillable = [
        'postulacion_id',
        'entrevistador_id',
        'inicia_at',
        'duracion_min',
        'modalidad',
        'enlace',
        'estado',
        'notas',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'duracion_min' => 45,
        'modalidad' => 'videollamada',
        'estado' => 'pendiente',
    ];

    protected function casts(): array
    {
        return [
            'inicia_at' => 'datetime',
            'duracion_min' => 'integer',
            'modalidad' => ModalidadEntrevista::class,
            'estado' => EstadoEntrevista::class,
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
    public function entrevistador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entrevistador_id');
    }
}

<?php

namespace App\Models;

use App\Enums\EstadoVacante;
use App\Enums\Modalidad;
use App\Tenancy\BelongsToEmpresa;
use Database\Factories\VacanteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $empresa_id
 * @property string $titulo
 * @property string|null $ubicacion
 * @property Modalidad $modalidad
 * @property EstadoVacante $estado
 * @property Carbon|null $publicada_at
 * @property Carbon $created_at
 */
class Vacante extends Model
{
    /** @use HasFactory<VacanteFactory> */
    use BelongsToEmpresa, HasFactory;

    protected $table = 'vacantes';

    protected $fillable = [
        'titulo',
        'descripcion',
        'ubicacion',
        'modalidad',
        'estado',
        'publicada_at',
        'cerrada_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'modalidad' => 'remoto',
        'estado' => 'borrador',
    ];

    protected function casts(): array
    {
        return [
            'modalidad' => Modalidad::class,
            'estado' => EstadoVacante::class,
            'publicada_at' => 'datetime',
            'cerrada_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por');
    }

    /**
     * @return HasMany<Requisito, $this>
     */
    public function requisitos(): HasMany
    {
        return $this->hasMany(Requisito::class)->orderBy('orden');
    }

    /**
     * @return HasMany<Postulacion, $this>
     */
    public function postulaciones(): HasMany
    {
        return $this->hasMany(Postulacion::class);
    }
}

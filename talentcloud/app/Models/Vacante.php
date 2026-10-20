<?php

namespace App\Models;

use App\Enums\EstadoVacante;
use App\Enums\Jornada;
use App\Enums\Modalidad;
use App\Support\BolsaPublica;
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
 * @property Jornada|null $jornada
 * @property int|null $sueldo_minimo Soles al mes.
 * @property int|null $sueldo_maximo Soles al mes.
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
        'jornada',
        'sueldo_minimo',
        'sueldo_maximo',
        'estado',
        'publicada_at',
        'cerrada_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'modalidad' => 'remoto',
        'estado' => 'borrador',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => BolsaPublica::olvidar());
        static::deleted(fn () => BolsaPublica::olvidar());
    }

    protected function casts(): array
    {
        return [
            'modalidad' => Modalidad::class,
            'jornada' => Jornada::class,
            'sueldo_minimo' => 'integer',
            'sueldo_maximo' => 'integer',
            'estado' => EstadoVacante::class,
            'publicada_at' => 'datetime',
            'cerrada_at' => 'datetime',
        ];
    }

    /**
     * "Madrid · Presencial", "Remoto" o "Perú · Remoto" (la cobertura de una vacante
     * remota; siempre primero el lugar y luego la modalidad). Si el lugar repite la modalidad ("Remoto · Remoto"), se muestra una sola vez.
     */
    /**
     * El sueldo mensual escrito: «S/ 1 500 – 2 000 al mes», «Desde S/ 1 500 al mes» o «Hasta S/ 2 000 al mes»; null si la
     * empresa no lo indicó.
     */
    public function sueldoEnTexto(): ?string
    {
        $monto = fn (int $soles): string => number_format($soles, 0, ',', ' ');

        return match (true) {
            $this->sueldo_minimo !== null && $this->sueldo_maximo !== null && $this->sueldo_minimo === $this->sueldo_maximo => 'S/ '.$monto($this->sueldo_minimo).' al mes',
            $this->sueldo_minimo !== null && $this->sueldo_maximo !== null => 'S/ '.$monto($this->sueldo_minimo).' – '.$monto($this->sueldo_maximo).' al mes',
            $this->sueldo_minimo !== null => 'Desde S/ '.$monto($this->sueldo_minimo).' al mes',
            $this->sueldo_maximo !== null => 'Hasta S/ '.$monto($this->sueldo_maximo).' al mes',
            default => null,
        };
    }

    public function lugarYModalidad(): string
    {
        $modalidad = $this->modalidad->etiqueta();
        $lugar = $this->modalidad->normalizarUbicacion($this->ubicacion);

        if ($lugar === null) {
            return $modalidad;
        }

        return "{$lugar} · {$modalidad}";
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

<?php

namespace App\Models;

use App\Enums\EstadoProcesamientoCv;
use Database\Factories\CvFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Archivo de CV de un candidato (disco privado). Global como el candidato.
 *
 * @property int $id
 * @property int $candidato_id
 * @property string $disco
 * @property string $ruta
 * @property string $nombre_original
 * @property string|null $texto_extraido
 * @property array<string, mixed>|null $datos_estructurados
 * @property EstadoProcesamientoCv $estado_procesamiento
 * @property string|null $error
 * @property Carbon|null $procesado_at
 */
class Cv extends Model
{
    /** @use HasFactory<CvFactory> */
    use HasFactory;

    protected $table = 'cvs';

    protected $fillable = [
        'candidato_id',
        'subido_por',
        'disco',
        'ruta',
        'nombre_original',
        'mime',
        'tamano',
        'texto_extraido',
        'datos_estructurados',
        'estado_procesamiento',
        'error',
        'procesado_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'estado_procesamiento' => 'pendiente',
    ];

    protected function casts(): array
    {
        return [
            'datos_estructurados' => 'array',
            'estado_procesamiento' => EstadoProcesamientoCv::class,
            'procesado_at' => 'datetime',
            'tamano' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Candidato, $this>
     */
    public function candidato(): BelongsTo
    {
        return $this->belongsTo(Candidato::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    /**
     * @return HasMany<CvFragmento, $this>
     */
    public function fragmentos(): HasMany
    {
        return $this->hasMany(CvFragmento::class)->orderBy('orden');
    }
}

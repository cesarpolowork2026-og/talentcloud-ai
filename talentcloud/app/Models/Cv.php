<?php

namespace App\Models;

use App\Enums\EstadoProcesamientoCv;
use Closure;
use Database\Factories\CvFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
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
            'estado_procesamiento' => EstadoProcesamientoCv::class,
            'procesado_at' => 'datetime',
            'tamano' => 'integer',
        ];
    }

    /**
     * Restricción para cargar el CV en pantallas y sondeos: sin el texto completo
     * (decenas de KB) ni los datos estructurados, solo un extracto de 1 000 caracteres
     * (las vistas muestran 180-220). Quien necesite el texto lee el modelo completo.
     *
     * @return Closure(Relation<*, *, *>): mixed
     */
    public static function ligero(): Closure
    {
        return fn (Relation $relacion) => $relacion
            ->select(['cvs.id', 'cvs.candidato_id', 'cvs.nombre_original', 'cvs.estado_procesamiento', 'cvs.error', 'cvs.created_at', 'cvs.procesado_at'])
            ->selectRaw('left(cvs.texto_extraido, 1000) as texto_extraido');
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
}

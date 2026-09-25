<?php

namespace App\Models;

use App\Enums\EtapaPostulacion;
use App\Tenancy\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Entrada de una postulación en una etapa (historial). Solo la escribe
 * PostulacionObserver; la postulación y la empresa las fija él.
 *
 * @property int $id
 * @property int $empresa_id
 * @property int $postulacion_id
 * @property EtapaPostulacion $etapa
 * @property Carbon $desde
 */
class PostulacionEtapa extends Model
{
    use BelongsToEmpresa;

    public $timestamps = false;

    protected $table = 'postulacion_etapas';

    protected $fillable = ['etapa', 'desde'];

    protected function casts(): array
    {
        return [
            'etapa' => EtapaPostulacion::class,
            'desde' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Postulacion, $this>
     */
    public function postulacion(): BelongsTo
    {
        return $this->belongsTo(Postulacion::class);
    }
}

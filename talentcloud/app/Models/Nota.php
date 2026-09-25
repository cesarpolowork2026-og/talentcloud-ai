<?php

namespace App\Models;

use App\Tenancy\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Nota interna del equipo sobre una postulación. Solo se asigna en masa el
 * texto: la postulación y el autor los fija el servicio.
 *
 * @property int $id
 * @property int $empresa_id
 * @property int $postulacion_id
 * @property int|null $autor_id
 * @property string $texto
 * @property Carbon $created_at
 */
class Nota extends Model
{
    use BelongsToEmpresa;

    protected $table = 'notas';

    protected $fillable = ['texto'];

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
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }
}

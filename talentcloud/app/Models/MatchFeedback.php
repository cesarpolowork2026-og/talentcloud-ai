<?php

namespace App\Models;

use App\Tenancy\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Marcar como incorrecto" en la pantalla de Matching.
 *
 * @property int $id
 * @property int $postulacion_id
 * @property int $requisito_id
 */
class MatchFeedback extends Model
{
    use BelongsToEmpresa;

    protected $table = 'match_feedback';

    protected $fillable = ['postulacion_id', 'requisito_id', 'user_id', 'comentario'];

    /**
     * @return BelongsTo<Postulacion, $this>
     */
    public function postulacion(): BelongsTo
    {
        return $this->belongsTo(Postulacion::class);
    }

    /**
     * @return BelongsTo<Requisito, $this>
     */
    public function requisito(): BelongsTo
    {
        return $this->belongsTo(Requisito::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

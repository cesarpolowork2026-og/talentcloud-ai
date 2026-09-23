<?php

namespace App\Models;

use App\Tenancy\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Entrada del feed "Actividad reciente" del dashboard.
 *
 * @property int $id
 * @property string $tipo
 * @property string $descripcion
 */
class Actividad extends Model
{
    use BelongsToEmpresa;

    protected $table = 'actividades';

    protected $fillable = ['actor_id', 'tipo', 'descripcion', 'sujeto_type', 'sujeto_id'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function sujeto(): MorphTo
    {
        return $this->morphTo();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\Vector;

/**
 * Fragmento del texto de un CV con su embedding (evidencia del matching).
 *
 * @property int $id
 * @property int $cv_id
 * @property string|null $seccion
 * @property int $orden
 * @property string $texto
 * @property Vector|null $embedding
 * @property string|null $embedding_modelo
 */
class CvFragmento extends Model
{
    protected $table = 'cv_fragmentos';

    protected $fillable = ['cv_id', 'seccion', 'orden', 'texto', 'embedding', 'embedding_modelo'];

    protected $hidden = ['embedding'];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'embedding' => Vector::class,
        ];
    }

    /**
     * @return BelongsTo<Cv, $this>
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(Cv::class);
    }
}

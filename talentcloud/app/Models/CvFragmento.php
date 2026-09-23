<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fragmento del texto de un CV con su embedding (evidencia del matching).
 *
 * @property int $id
 * @property int $cv_id
 * @property string|null $seccion
 * @property string $texto
 */
class CvFragmento extends Model
{
    protected $table = 'cv_fragmentos';

    protected $fillable = ['cv_id', 'seccion', 'orden', 'texto'];

    protected $hidden = ['embedding'];

    /**
     * @return BelongsTo<Cv, $this>
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(Cv::class);
    }
}

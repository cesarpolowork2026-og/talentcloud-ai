<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una acción del administrador SaaS (ver RegistroDeAuditoria). Solo se crea: no se edita.
 *
 * @property int $id
 * @property int|null $usuario_id
 * @property string $usuario_nombre
 * @property string $accion
 * @property string $descripcion
 * @property int|null $empresa_id
 * @property Carbon $created_at
 */
class Auditoria extends Model
{
    protected $table = 'auditoria';

    public $timestamps = false;

    /** @var list<string> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class)->withoutGlobalScopes();
    }
}

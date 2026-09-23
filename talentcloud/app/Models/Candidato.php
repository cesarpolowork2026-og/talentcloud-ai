<?php

namespace App\Models;

use Database\Factories\CandidatoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Persona candidata. Es global (sin BelongsToEmpresa): un postulante aplica a
 * varias empresas. La visibilidad por empresa se aplica en su repositorio y
 * en su Policy (postuló a una vacante de la empresa o la empresa lo registró).
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $empresa_origen_id
 * @property string $nombre
 * @property Carbon|null $consentimiento_at
 */
class Candidato extends Model
{
    /** @use HasFactory<CandidatoFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'empresa_origen_id',
        'nombre',
        'email',
        'telefono',
        'titular',
        'consentimiento_at',
    ];

    protected function casts(): array
    {
        return [
            'consentimiento_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresaOrigen(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_origen_id');
    }
}

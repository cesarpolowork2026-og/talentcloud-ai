<?php

namespace App\Models;

use App\Enums\Rol;
use App\Tenancy\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Invitación de la empresa a un nuevo usuario interno (reclutador o
 * administrador de la empresa). Solo se guarda el hash del token.
 *
 * @property int $id
 * @property string $email
 * @property Rol $rol
 * @property Carbon $expira_at
 * @property Carbon|null $aceptada_at
 */
class Invitacion extends Model
{
    use BelongsToEmpresa;

    protected $table = 'invitaciones';

    protected $fillable = ['nombre', 'email', 'rol', 'token_hash', 'expira_at', 'aceptada_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'rol' => Rol::class,
            'expira_at' => 'datetime',
            'aceptada_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitada_por');
    }

    public function estaPendiente(): bool
    {
        return $this->aceptada_at === null && $this->expira_at->isFuture();
    }
}

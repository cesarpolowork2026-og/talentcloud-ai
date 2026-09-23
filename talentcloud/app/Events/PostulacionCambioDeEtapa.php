<?php

namespace App\Events;

use App\Enums\EtapaPostulacion;
use App\Models\Postulacion;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Una postulación pasó a otra columna del Kanban (actividad y notificaciones: día 18). */
class PostulacionCambioDeEtapa
{
    use Dispatchable;

    public function __construct(
        public readonly Postulacion $postulacion,
        public readonly EtapaPostulacion $origen,
        public readonly EtapaPostulacion $destino,
        public readonly ?User $actor,
    ) {}
}

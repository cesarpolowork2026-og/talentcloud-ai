<?php

namespace App\Events;

use App\Models\Postulacion;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** El postulante retiró su postulación: aviso a la empresa y línea en la actividad. */
class PostulacionRetirada
{
    use Dispatchable;

    public function __construct(
        public readonly Postulacion $postulacion,
        public readonly User $postulante,
    ) {}
}

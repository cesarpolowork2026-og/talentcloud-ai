<?php

namespace App\Events;

use App\Models\Reclamacion;
use Illuminate\Foundation\Events\Dispatchable;

/** Se presentó una hoja del Libro de Reclamaciones: copia a soporte y al consumidor, aviso a los admins. */
class ReclamacionRegistrada
{
    use Dispatchable;

    public function __construct(public readonly Reclamacion $reclamacion) {}
}

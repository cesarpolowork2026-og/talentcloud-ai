<?php

namespace App\Events;

use App\Models\Postulacion;
use Illuminate\Foundation\Events\Dispatchable;

class PostulacionCreada
{
    use Dispatchable;

    public function __construct(public readonly Postulacion $postulacion) {}
}

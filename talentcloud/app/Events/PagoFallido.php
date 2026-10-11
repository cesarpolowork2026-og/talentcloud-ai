<?php

namespace App\Events;

use App\Models\Empresa;
use Illuminate\Foundation\Events\Dispatchable;

/** La pasarela no pudo cobrar la renovación del plan (y lo reintentará): aviso a la cuenta de empresa. */
class PagoFallido
{
    use Dispatchable;

    public function __construct(public readonly Empresa $empresa) {}
}

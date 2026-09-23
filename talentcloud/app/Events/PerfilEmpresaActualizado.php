<?php

namespace App\Events;

use App\Models\Empresa;
use Illuminate\Foundation\Events\Dispatchable;

/** La empresa guardó sus datos ("Completa el perfil de tu empresa"). */
class PerfilEmpresaActualizado
{
    use Dispatchable;

    public function __construct(public readonly Empresa $empresa) {}
}

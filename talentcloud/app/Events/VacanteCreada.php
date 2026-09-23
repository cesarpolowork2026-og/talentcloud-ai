<?php

namespace App\Events;

use App\Models\User;
use App\Models\Vacante;
use Illuminate\Foundation\Events\Dispatchable;

class VacanteCreada
{
    use Dispatchable;

    public function __construct(
        public readonly Vacante $vacante,
        public readonly ?User $autor,
    ) {}
}

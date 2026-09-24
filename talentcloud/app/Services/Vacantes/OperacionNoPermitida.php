<?php

namespace App\Services\Vacantes;

use DomainException;

/** Regla de negocio de vacantes incumplida; el mensaje se muestra al usuario. */
class OperacionNoPermitida extends DomainException
{
    /** @param  string|null  $campo  Campo del formulario al que corresponde el error (el componente lo muestra ahí). */
    public function __construct(string $mensaje = '', public readonly ?string $campo = null)
    {
        parent::__construct($mensaje);
    }
}

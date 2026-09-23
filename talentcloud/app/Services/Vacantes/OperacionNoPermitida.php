<?php

namespace App\Services\Vacantes;

use DomainException;

/** Regla de negocio de vacantes incumplida; el mensaje se muestra al usuario. */
class OperacionNoPermitida extends DomainException {}

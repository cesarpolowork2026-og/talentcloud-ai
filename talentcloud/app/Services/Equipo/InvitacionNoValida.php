<?php

namespace App\Services\Equipo;

use DomainException;

/** Regla de negocio de invitaciones incumplida (correo ya registrado, rol no permitido, invitación vencida…). */
class InvitacionNoValida extends DomainException {}

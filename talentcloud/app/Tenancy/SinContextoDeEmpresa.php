<?php

namespace App\Tenancy;

use LogicException;

/** Se intentó crear un registro de empresa sin empresa_id ni contexto de tenant. */
class SinContextoDeEmpresa extends LogicException {}

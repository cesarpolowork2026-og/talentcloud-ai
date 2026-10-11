<?php

namespace App\Pagos;

use RuntimeException;

/** Un aviso que no firmó la pasarela (o que no se puede leer): se rechaza sin tocar nada. */
class AvisoNoValido extends RuntimeException {}

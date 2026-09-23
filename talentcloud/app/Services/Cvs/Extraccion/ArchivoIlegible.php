<?php

namespace App\Services\Cvs\Extraccion;

use RuntimeException;

/** No se pudo obtener texto del CV. El mensaje se muestra al usuario. */
class ArchivoIlegible extends RuntimeException {}

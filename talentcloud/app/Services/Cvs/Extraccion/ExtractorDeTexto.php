<?php

namespace App\Services\Cvs\Extraccion;

/** Adapter: obtiene el texto plano de un formato de CV. */
interface ExtractorDeTexto
{
    public function soporta(string $mime): bool;

    /**
     * @param  string  $contenido  Bytes del archivo.
     *
     * @throws ArchivoIlegible
     */
    public function extraer(string $contenido): string;
}

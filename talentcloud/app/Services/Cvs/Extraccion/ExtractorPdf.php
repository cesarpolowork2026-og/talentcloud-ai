<?php

namespace App\Services\Cvs\Extraccion;

use Smalot\PdfParser\Parser;
use Throwable;

class ExtractorPdf implements ExtractorDeTexto
{
    public function soporta(string $mime): bool
    {
        return $mime === 'application/pdf';
    }

    public function extraer(string $contenido): string
    {
        try {
            return (new Parser)->parseContent($contenido)->getText();
        } catch (Throwable $e) {
            throw new ArchivoIlegible('No se pudo leer el PDF. Verifica que no esté dañado ni protegido con contraseña.', previous: $e);
        }
    }
}

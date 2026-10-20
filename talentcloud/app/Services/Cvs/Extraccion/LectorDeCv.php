<?php

namespace App\Services\Cvs\Extraccion;

/**
 * Elige el extractor según el tipo de archivo y normaliza el texto. Un PDF
 * escaneado (solo imagen) no tiene texto: se informa como error, el OCR
 * queda fuera del alcance actual.
 */
class LectorDeCv
{
    /** Menos caracteres útiles que esto se considera "sin texto". */
    private const MINIMO_CARACTERES = 40;

    /**
     * Tope de caracteres que se conservan (un tope razonable para guardar el texto del CV):
     * un CV real es mucho menor; un documento más largo se recorta en vez de quedar sin análisis.
     */
    public const MAXIMO_CARACTERES = 200_000;

    /** @var list<ExtractorDeTexto> */
    private array $extractores;

    public function __construct(ExtractorPdf $pdf, ExtractorDocx $docx)
    {
        $this->extractores = [$pdf, $docx];
    }

    /**
     * @throws ArchivoIlegible
     */
    public function leer(string $contenido, string $mime): string
    {
        foreach ($this->extractores as $extractor) {
            if ($extractor->soporta($mime)) {
                $texto = self::normalizar($extractor->extraer($contenido));

                if (mb_strlen(preg_replace('/\s+/u', '', $texto) ?? '') < self::MINIMO_CARACTERES) {
                    throw new ArchivoIlegible('El archivo no contiene texto seleccionable (parece un documento escaneado). Sube una versión en PDF con texto o en Word.');
                }

                return mb_substr($texto, 0, self::MAXIMO_CARACTERES);
            }
        }

        throw new ArchivoIlegible('Formato no admitido. Sube el CV en PDF o Word (.docx).');
    }

    /** Une espacios repetidos, conserva saltos de párrafo y quita caracteres de control. */
    public static function normalizar(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $texto);
        $texto = preg_replace('/[^\P{C}\n\t]+/u', '', $texto) ?? $texto;
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/ *\n */u', "\n", $texto) ?? $texto;
        $texto = preg_replace('/\n{3,}/u', "\n\n", $texto) ?? $texto;

        return trim($texto);
    }
}

<?php

namespace Database\Seeders\Demo;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;

/**
 * Genera CVs reales (bytes de PDF o DOCX) para los datos de demostración y
 * los tests, sin archivos binarios en el repositorio.
 */
class DocumentosDePrueba
{
    /** DOCX con un párrafo por línea. */
    public static function docx(string $texto): string
    {
        $documento = new PhpWord;
        $seccion = $documento->addSection();

        foreach (explode("\n", $texto) as $linea) {
            $linea === '' ? $seccion->addTextBreak() : $seccion->addText(htmlspecialchars($linea, ENT_QUOTES | ENT_XML1));
        }

        $temporal = tempnam(sys_get_temp_dir(), 'docx');

        if ($temporal === false) {
            throw new RuntimeException('No se pudo crear un archivo temporal.');
        }

        try {
            IOFactory::createWriter($documento, 'Word2007')->save($temporal);

            return (string) file_get_contents($temporal);
        } finally {
            @unlink($temporal);
        }
    }

    /**
     * PDF de una página. Con $texto null no tiene texto seleccionable, como
     * un documento escaneado.
     */
    public static function pdf(?string $texto): string
    {
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        ];

        if ($texto === null) {
            $objetos[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>';
        } else {
            $lineas = array_map(
                fn (string $l) => '('.self::escapar($l).') Tj T*',
                explode("\n", $texto),
            );
            $contenido = "BT /F1 10 Tf 14 TL 50 800 Td\n".implode("\n", $lineas)."\nET";

            $objetos[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>';
            $objetos[4] = '<< /Length '.strlen($contenido)." >>\nstream\n".$contenido."\nendstream";
            $objetos[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        }

        $pdf = "%PDF-1.4\n";
        $posiciones = [];

        foreach ($objetos as $numero => $cuerpo) {
            $posiciones[$numero] = strlen($pdf);
            $pdf .= $numero." 0 obj\n".$cuerpo."\nendobj\n";
        }

        $inicioXref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objetos) + 1)."\n0000000000 65535 f \n";
        foreach ($posiciones as $posicion) {
            $pdf .= sprintf("%010d 00000 n \n", $posicion);
        }
        $pdf .= "trailer\n<< /Size ".(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n".$inicioXref."\n%%EOF\n";

        return $pdf;
    }

    /** Texto para una cadena literal de PDF en WinAnsi (admite tildes y ñ). */
    private static function escapar(string $linea): string
    {
        $linea = (string) mb_convert_encoding($linea, 'Windows-1252', 'UTF-8');

        return strtr($linea, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    }
}

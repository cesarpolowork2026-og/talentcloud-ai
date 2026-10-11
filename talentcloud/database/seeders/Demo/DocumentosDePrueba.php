<?php

namespace Database\Seeders\Demo;

use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use RuntimeException;

/**
 * Genera CVs reales (bytes de PDF o DOCX) para los datos de demostración y
 * los tests, sin archivos binarios en el repositorio.
 *
 * El texto se maqueta como un CV clásico de una columna: nombre destacado,
 * titular y contacto, títulos de sección con una línea debajo, puestos en
 * negrita y logros con viñetas. Cada línea del texto sigue siendo una línea
 * del documento (o varias, si no cabe en el ancho del PDF), así que el texto
 * que se extrae es el mismo que se escribió.
 */
class DocumentosDePrueba
{
    /** Títulos de sección que se maquetan como tales (los mismos que reconoce el servicio de IA). */
    private const ENCABEZADOS = '/^(perfil( profesional)?|resumen( profesional)?|sobre mi|objetivo( profesional)?'
        .'|experiencia( laboral| profesional)?|trayectoria( profesional| laboral)?|educacion|formacion( academica| profesional)?|estudios'
        .'|habilidades( tecnicas)?|competencias( tecnicas)?|conocimientos( tecnicos)?|herramientas|tecnologias|aptitudes|skills'
        .'|certificaciones( y cursos)?|cursos( y certificaciones)?|certificados|idiomas|proyectos( destacados| personales)?'
        .'|intereses|referencias( laborales| personales)?)$/';

    /** Secciones cuyas líneas con un año son un puesto, un estudio o un curso (van en negrita). */
    private const SECCIONES_CON_ENTRADAS = '/^(experiencia|trayectoria|educacion|formacion|estudios|proyectos)/';

    private const ACENTO = '0B4F6C';

    private const GRIS = '5F6B73';

    private const TINTA = '1F2328';

    /** DOCX con un párrafo por línea, con estilos de Word. */
    public static function docx(string $texto): string
    {
        $documento = new PhpWord;
        $documento->setDefaultFontName('Calibri');
        $documento->setDefaultFontSize(10);
        $seccion = $documento->addSection(['marginTop' => 1000, 'marginBottom' => 1000, 'marginLeft' => 1150, 'marginRight' => 1150]);

        foreach (self::lineas($texto) as [$tipo, $linea]) {
            $linea = htmlspecialchars($linea, ENT_QUOTES | ENT_XML1);

            match ($tipo) {
                'vacia' => $seccion->addTextBreak(1, ['size' => 4]),
                'nombre' => $seccion->addText($linea, ['size' => 22, 'bold' => true, 'color' => self::ACENTO], ['spaceAfter' => 0]),
                'titular' => $seccion->addText($linea, ['size' => 12.5, 'color' => self::TINTA], ['spaceAfter' => 40]),
                'contacto' => $seccion->addText($linea, ['size' => 9.5, 'color' => self::GRIS], ['spaceAfter' => 60]),
                'encabezado' => $seccion->addText($linea, ['size' => 11, 'bold' => true, 'allCaps' => true, 'color' => self::ACENTO], [
                    'spaceBefore' => 200, 'spaceAfter' => 80, 'borderBottomSize' => 8, 'borderBottomColor' => self::ACENTO,
                ]),
                'entrada' => $seccion->addText($linea, ['bold' => true, 'color' => self::TINTA], ['spaceBefore' => 80, 'spaceAfter' => 20]),
                'vineta' => $seccion->addText($linea, ['color' => self::TINTA], ['indentation' => ['left' => 360, 'hanging' => 200], 'spaceAfter' => 20, 'alignment' => Jc::BOTH]),
                default => $seccion->addText($linea, ['color' => self::TINTA], ['spaceAfter' => 40, 'alignment' => Jc::BOTH]),
            };
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
     * PDF A4 (con las páginas que hagan falta). Con $texto null es una página
     * sin texto seleccionable, como un documento escaneado.
     */
    public static function pdf(?string $texto): string
    {
        $paginas = $texto === null ? [''] : self::paginasPdf($texto);
        $total = count($paginas);

        // 1: catálogo, 2: páginas, 3 y 4: fuentes; luego cada página con su contenido.
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $hijos = [];

        foreach ($paginas as $i => $contenido) {
            $pagina = 5 + $i * 2;
            $hijos[] = $pagina.' 0 R';

            if ($texto === null) {
                $objetos[$pagina] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>';
                $objetos[$pagina + 1] = "<< /Length 0 >>\nstream\n\nendstream";

                continue;
            }

            $objetos[$pagina] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.($pagina + 1).' 0 R >>';
            $objetos[$pagina + 1] = '<< /Length '.strlen($contenido)." >>\nstream\n".$contenido."\nendstream";
        }

        $objetos[2] = '<< /Type /Pages /Kids ['.implode(' ', $hijos).'] /Count '.$total.' >>';
        ksort($objetos);

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

    /**
     * Clasifica cada línea: nombre (la primera), titular y contacto (hasta el
     * primer título de sección), títulos, entradas con año, viñetas y texto.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function lineas(string $texto): array
    {
        $resultado = [];
        $enEncabezado = true;
        $seccion = '';
        $lineasDeEncabezado = 0;

        foreach (explode("\n", $texto) as $linea) {
            $linea = rtrim($linea);
            $normal = Str::lower(Str::ascii(rtrim($linea, ':')));

            if ($linea === '') {
                $resultado[] = ['vacia', ''];
            } elseif (preg_match(self::ENCABEZADOS, $normal) === 1) {
                $enEncabezado = false;
                $seccion = $normal;
                $resultado[] = ['encabezado', $linea];
            } elseif ($enEncabezado) {
                $resultado[] = [match ($lineasDeEncabezado++) {
                    0 => 'nombre',
                    1 => 'titular',
                    default => 'contacto',
                }, $linea];
            } elseif (str_starts_with($linea, '•')) {
                $resultado[] = ['vineta', $linea];
            } elseif (preg_match(self::SECCIONES_CON_ENTRADAS, $seccion) === 1 && preg_match('/\b(19|20)\d{2}\b/', $linea) === 1) {
                $resultado[] = ['entrada', $linea];
            } else {
                $resultado[] = ['texto', $linea];
            }
        }

        return $resultado;
    }

    /**
     * Contenido de cada página del PDF, con las líneas ajustadas al ancho.
     *
     * @return list<string>
     */
    private static function paginasPdf(string $texto): array
    {
        $izquierda = 56.0;
        $ancho = 595.0 - 2 * $izquierda;
        $arriba = 842.0 - 58;
        $abajo = 58.0;

        $paginas = [];
        $operaciones = [];
        $y = $arriba;

        $nuevaPagina = function () use (&$paginas, &$operaciones, &$y, $arriba): void {
            $paginas[] = implode("\n", $operaciones);
            $operaciones = [];
            $y = $arriba;
        };

        foreach (self::lineas($texto) as [$tipo, $linea]) {
            [$fuente, $tamano, $color, $antes, $sangria] = match ($tipo) {
                'nombre' => ['F2', 21.0, self::ACENTO, 0.0, 0.0],
                'titular' => ['F1', 12.5, self::TINTA, 4.0, 0.0],
                'contacto' => ['F1', 9.5, self::GRIS, 1.0, 0.0],
                'encabezado' => ['F2', 10.5, self::ACENTO, 12.0, 0.0],
                'entrada' => ['F2', 10.0, self::TINTA, 5.0, 0.0],
                'vineta' => ['F1', 9.8, self::TINTA, 0.0, 8.0],
                default => ['F1', 9.8, self::TINTA, 0.0, 0.0],
            };

            if ($tipo === 'vacia') {
                $y -= 4;

                continue;
            }

            $linea = $tipo === 'encabezado' ? mb_strtoupper($linea) : $linea;
            $interlineado = $tamano * 1.32;
            $partes = self::ajustar($linea, $ancho - $sangria, $tamano, $fuente === 'F2');
            // Continuación de una viñeta: alineada con el texto, no con el símbolo.
            $sangriaSiguiente = $tipo === 'vineta' ? $sangria + self::anchoTexto('• ', $tamano, false) : $sangria;

            $y -= $antes;
            foreach ($partes as $i => $parte) {
                if ($y - $interlineado < $abajo) {
                    $nuevaPagina();
                }

                $y -= $interlineado;
                $x = $izquierda + ($i === 0 ? $sangria : $sangriaSiguiente);
                $operaciones[] = sprintf('BT /%s %.1F Tf %s rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET', $fuente, $tamano, self::rgb($color), $x, $y, self::escapar($parte));
            }

            if ($tipo === 'encabezado') {
                $y -= 3;
                $operaciones[] = sprintf('%s RG 0.8 w %.2F %.2F m %.2F %.2F l S', self::rgb(self::ACENTO), $izquierda, $y, $izquierda + $ancho, $y);
                $y -= 4;
            }
        }

        $nuevaPagina();

        return $paginas;
    }

    /**
     * Parte la línea en trozos que caben en el ancho (por palabras).
     *
     * @return list<string>
     */
    private static function ajustar(string $linea, float $ancho, float $tamano, bool $negrita): array
    {
        $partes = [];
        $actual = '';

        foreach (self::unidadesDeCorte($linea) as $palabra) {
            $candidata = $actual === '' ? $palabra : $actual.' '.$palabra;

            if ($actual !== '' && self::anchoTexto($candidata, $tamano, $negrita) > $ancho) {
                $partes[] = $actual;
                $actual = $palabra;
            } else {
                $actual = $candidata;
            }
        }

        $partes[] = $actual;

        return $partes;
    }

    /**
     * Palabras que pueden ir al final de una línea. Un año nunca queda al
     * principio ni al final de un trozo: una línea como "2024 (hasta 118 %)"
     * parecería un puesto con su periodo al leer el CV.
     *
     * @return list<string>
     */
    private static function unidadesDeCorte(string $linea): array
    {
        $unidades = [];
        $unirConLaSiguiente = false;

        foreach (explode(' ', $linea) as $palabra) {
            $esAnio = preg_match('/^\(?(19|20)\d{2}\b/', $palabra) === 1;

            if ($unidades !== [] && ($esAnio || $unirConLaSiguiente)) {
                $unidades[count($unidades) - 1] .= ' '.$palabra;
            } else {
                $unidades[] = $palabra;
            }

            $unirConLaSiguiente = $esAnio;
        }

        return $unidades;
    }

    /** Ancho aproximado del texto en Helvetica (métricas AFM, en milésimas del tamaño). */
    private static function anchoTexto(string $texto, float $tamano, bool $negrita): float
    {
        static $anchos = null;
        $anchos ??= self::metricasHelvetica();

        $total = 0;
        foreach (mb_str_split($texto) as $caracter) {
            $base = Str::ascii($caracter);
            // Las cifras miden lo mismo que el valor por defecto (556).
            $total += $anchos[$caracter] ?? $anchos[$base] ?? 556;
        }

        // Helvetica-Bold es algo más ancha: se reserva un margen.
        return $total * $tamano / 1000 * ($negrita ? 1.08 : 1.0);
    }

    /** @return array<string, int> */
    private static function metricasHelvetica(): array
    {
        $anchos = [
            ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, "'" => 191, '(' => 333, ')' => 333,
            '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278, ':' => 278, ';' => 278, '<' => 584, '=' => 584,
            '>' => 584, '?' => 556, '@' => 1015, '[' => 278, '\\' => 278, ']' => 278, '_' => 556, '|' => 260,
            '•' => 350, '–' => 556, '—' => 1000, '·' => 278, '«' => 556, '»' => 556, '¿' => 611, '¡' => 333, '°' => 400, 'º' => 365,
        ];
        $letras = [
            'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722, 'I' => 278, 'J' => 500,
            'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778, 'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611,
            'U' => 722, 'V' => 667, 'W' => 944, 'X' => 667, 'Y' => 667, 'Z' => 611,
            'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556, 'h' => 556, 'i' => 222, 'j' => 222,
            'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556, 'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 't' => 278,
            'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500, 'z' => 500,
        ];

        return $anchos + $letras;
    }

    private static function rgb(string $hex): string
    {
        return implode(' ', array_map(fn (string $c) => sprintf('%.3F', hexdec($c) / 255), str_split($hex, 2)));
    }

    /** Texto para una cadena literal de PDF en WinAnsi (admite tildes y ñ). */
    private static function escapar(string $linea): string
    {
        $linea = (string) mb_convert_encoding($linea, 'Windows-1252', 'UTF-8');

        return strtr($linea, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    }
}

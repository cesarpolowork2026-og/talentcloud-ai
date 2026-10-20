<?php

namespace App\Support;

/**
 * Busca palabras o frases completas dentro de un texto, sin distinguir mayúsculas ni tildes («Administración» encuentra
 * «administracion»). Es una búsqueda exacta de palabras, no semántica: «C++» no se confunde con «C», ni «Java» con
 * «JavaScript». Los términos de una o dos letras («C», «R», «Go») se buscan respetando las mayúsculas, porque si no
 * coincidirían con casi cualquier texto.
 *
 * Las posiciones se dan en caracteres sobre el texto original (la normalización conserva la longitud).
 */
final class BuscadorDeTerminos
{
    private const SIN_TILDES = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
    ];

    /** Minúsculas y sin tildes, con la misma cantidad de caracteres que el original. */
    public static function normalizar(string $texto): string
    {
        return strtr(mb_strtolower($texto), self::SIN_TILDES);
    }

    /**
     * Zonas del texto donde aparece alguno de los términos: [inicio, largo] en caracteres, ordenadas y sin solaparse.
     *
     * @param  list<string>  $terminos
     * @return list<array{0: int, 1: int}>
     */
    public static function posiciones(string $texto, array $terminos, bool $soloLaPrimera = false): array
    {
        if ($texto === '') {
            return [];
        }

        $normalizado = self::normalizar($texto);
        $zonas = [];

        foreach (array_unique($terminos) as $termino) {
            $termino = trim($termino);
            if ($termino === '') {
                continue;
            }

            $corto = mb_strlen($termino) <= 2;
            $patron = '/(?<![\p{L}\p{N}])'.preg_quote($corto ? $termino : self::normalizar($termino), '/').'(?![\p{L}\p{N}])/u';

            if (preg_match_all($patron, $corto ? $texto : $normalizado, $encontrados, PREG_OFFSET_CAPTURE) === false) {
                continue;
            }

            foreach ($encontrados[0] as [$hallado, $byte]) {
                $inicio = mb_strlen(substr($corto ? $texto : $normalizado, 0, $byte));
                $zonas[] = [$inicio, mb_strlen($hallado)];

                if ($soloLaPrimera) {
                    break;
                }
            }
        }

        usort($zonas, fn (array $a, array $b) => $a[0] <=> $b[0] ?: $b[1] <=> $a[1]);

        // Se funden las que se tocan o solapan («Power BI» y «BI»).
        $unidas = [];
        foreach ($zonas as [$inicio, $largo]) {
            $ultimo = count($unidas) - 1;
            if ($ultimo >= 0 && $inicio <= $unidas[$ultimo][0] + $unidas[$ultimo][1]) {
                $fin = max($unidas[$ultimo][0] + $unidas[$ultimo][1], $inicio + $largo);
                $unidas[$ultimo][1] = $fin - $unidas[$ultimo][0];

                continue;
            }
            $unidas[] = [$inicio, $largo];
        }

        return $unidas;
    }

    /**
     * El texto partido en tramos, marcando los que coinciden con algún término.
     *
     * @param  list<string>  $terminos
     * @return list<array{texto: string, marcado: bool}>
     */
    public static function tramos(string $texto, array $terminos): array
    {
        $tramos = [];
        $cursor = 0;

        foreach (self::posiciones($texto, $terminos) as [$inicio, $largo]) {
            if ($inicio > $cursor) {
                $tramos[] = ['texto' => mb_substr($texto, $cursor, $inicio - $cursor), 'marcado' => false];
            }
            $tramos[] = ['texto' => mb_substr($texto, $inicio, $largo), 'marcado' => true];
            $cursor = $inicio + $largo;
        }

        if ($cursor < mb_strlen($texto)) {
            $tramos[] = ['texto' => mb_substr($texto, $cursor), 'marcado' => false];
        }

        return $tramos;
    }

    /** Un fragmento del texto alrededor de una zona, en una sola línea y con puntos suspensivos si se cortó. */
    public static function fragmento(string $texto, int $inicio, int $largo, int $margen = 70): string
    {
        $desde = max(0, $inicio - $margen);
        $hasta = min(mb_strlen($texto), $inicio + $largo + $margen);

        $corte = trim((string) preg_replace('/\s+/u', ' ', mb_substr($texto, $desde, $hasta - $desde)));

        return ($desde > 0 ? '… ' : '').$corte.($hasta < mb_strlen($texto) ? ' …' : '');
    }
}

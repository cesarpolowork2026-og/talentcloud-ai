<?php

namespace App\Matching;

use App\Enums\MotorMatching;
use App\Enums\NivelCoincidencia;
use Illuminate\Support\Str;

/**
 * Motor de respaldo sin IA: reglas ponderadas sobre palabras clave.
 *
 * Por requisito busca la competencia y sus sinónimos en el texto del CV
 * (sin tildes, con límites de palabra) y toma como evidencia la frase donde
 * aparece. Es el motor de los tests y el respaldo cuando el servicio de IA
 * no responde ("análisis básico"); no entiende sinónimos semánticos.
 */
class FakeMatchingEngine implements MatchingEngineInterface
{
    /** Menciones que no acreditan experiencia ("Kubernetes (interés en aprender)"). */
    private const MARCADORES_DUDOSOS = [
        'interes en aprender', 'interes en', 'quiero aprender', 'me gustaria aprender', 'aprendiendo',
        'en formacion', 'nociones', 'basico', 'basicos', 'conocimientos basicos', 'curso introductorio',
    ];

    private const UMBRAL_COINCIDE = 60;

    public function evaluar(MatchInput $entrada): MatchResult
    {
        $frases = $this->frases($entrada->textoCv);

        $resultados = array_map(fn (RequisitoInput $r) => $this->evaluarRequisito($r, $frases), $entrada->requisitos);

        $pesoTotal = array_sum(array_map(fn (RequisitoInput $r) => max(1, $r->peso), $entrada->requisitos));
        $score = $pesoTotal === 0 ? 0.0 : array_sum(array_map(
            fn (RequisitoInput $r, RequisitoMatch $m) => $m->porcentaje * max(1, $r->peso),
            $entrada->requisitos,
            $resultados,
        )) / $pesoTotal;

        return new MatchResult(
            score: round($score, 2),
            requisitos: $resultados,
            motor: MotorMatching::Fake,
            resumen: $this->resumen($resultados),
        );
    }

    /**
     * @param  list<array{original: string, normal: string}>  $frases
     */
    private function evaluarRequisito(RequisitoInput $requisito, array $frases): RequisitoMatch
    {
        $terminos = array_values(array_unique(array_filter(array_map(
            fn (string $t) => self::normalizar($t),
            [$requisito->competencia, ...$requisito->sinonimos],
        ))));

        $menciones = array_values(array_filter($frases, function (array $frase) use ($terminos): bool {
            foreach ($terminos as $termino) {
                if (preg_match('/(?<![a-z0-9])'.preg_quote($termino, '/').'(?![a-z0-9])/u', $frase['normal']) === 1) {
                    return true;
                }
            }

            return false;
        }));

        if ($menciones === []) {
            return new RequisitoMatch(
                requisitoId: $requisito->id,
                competencia: $requisito->competencia,
                porcentaje: 0,
                nivel: NivelCoincidencia::NoEncontrado,
                evidencia: null,
                motivo: 'No se encontró ninguna mención de '.$requisito->competencia.' en el CV.',
            );
        }

        $firmes = array_values(array_filter($menciones, fn (array $f) => ! $this->esDudosa($f['normal'])));

        if ($firmes === []) {
            return new RequisitoMatch(
                requisitoId: $requisito->id,
                competencia: $requisito->competencia,
                porcentaje: 20,
                nivel: NivelCoincidencia::Revisar,
                evidencia: $this->recortar($menciones[0]['original']),
                motivo: 'Se menciona como interés o nivel básico, sin experiencia declarada.',
            );
        }

        $porcentaje = 70 + min(2, count($firmes) - 1) * 5;
        $motivo = 'Coincidencia exacta de '.$requisito->competencia.' en el CV.';
        $anios = $this->aniosDeclarados($firmes);

        if ($anios !== null && $requisito->aniosMinimos !== null) {
            if ($anios >= $requisito->aniosMinimos) {
                $porcentaje += 20;
                $motivo = 'Declara '.$this->formatearAnios($anios).' de experiencia (se piden '.$requisito->aniosMinimos.').';
            } else {
                $porcentaje = 50;
                $motivo = 'Declara '.$this->formatearAnios($anios).' de experiencia; se piden '.$requisito->aniosMinimos.'.';
            }
        } elseif ($anios !== null) {
            $porcentaje += 15;
            $motivo = 'Declara '.$this->formatearAnios($anios).' de experiencia.';
        }

        $porcentaje = min(98, $porcentaje);

        return new RequisitoMatch(
            requisitoId: $requisito->id,
            competencia: $requisito->competencia,
            porcentaje: $porcentaje,
            nivel: $porcentaje >= self::UMBRAL_COINCIDE ? NivelCoincidencia::Coincide : NivelCoincidencia::Revisar,
            evidencia: $this->recortar(($this->fraseConAnios($firmes) ?? $firmes[0])['original']),
            motivo: $motivo,
        );
    }

    /**
     * Frases del CV (por línea y por punto), con su versión normalizada.
     *
     * @return list<array{original: string, normal: string}>
     */
    private function frases(string $texto): array
    {
        $frases = [];

        foreach (preg_split('/\n+/u', $texto) ?: [] as $linea) {
            foreach (preg_split('/(?<=[.!?;])\s+/u', trim($linea)) ?: [] as $frase) {
                $frase = trim($frase);

                if ($frase !== '') {
                    $frases[] = ['original' => $frase, 'normal' => self::normalizar($frase)];
                }
            }
        }

        return $frases;
    }

    /**
     * @param  list<array{original: string, normal: string}>  $frases
     */
    private function aniosDeclarados(array $frases): ?float
    {
        $frase = $this->fraseConAnios($frases);

        if ($frase === null || preg_match('/(\d+(?:[.,]\d+)?)\s+anos?\b/u', $frase['normal'], $m) !== 1) {
            return null;
        }

        return (float) str_replace(',', '.', $m[1]);
    }

    /**
     * @param  list<array{original: string, normal: string}>  $frases
     * @return array{original: string, normal: string}|null
     */
    private function fraseConAnios(array $frases): ?array
    {
        foreach ($frases as $frase) {
            if (preg_match('/\d+(?:[.,]\d+)?\s+anos?\b/u', $frase['normal']) === 1) {
                return $frase;
            }
        }

        return null;
    }

    private function esDudosa(string $fraseNormal): bool
    {
        foreach (self::MARCADORES_DUDOSOS as $marcador) {
            if (str_contains($fraseNormal, $marcador)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<RequisitoMatch>  $resultados
     */
    private function resumen(array $resultados): string
    {
        $cuenta = fn (NivelCoincidencia $n) => count(array_filter($resultados, fn (RequisitoMatch $r) => $r->nivel === $n));

        return sprintf(
            'Coincide en %d de %d requisitos; %d para revisar y %d sin evidencia en el CV.',
            $cuenta(NivelCoincidencia::Coincide),
            count($resultados),
            $cuenta(NivelCoincidencia::Revisar),
            $cuenta(NivelCoincidencia::NoEncontrado),
        );
    }

    private function formatearAnios(float $anios): string
    {
        $numero = rtrim(rtrim(number_format($anios, 1, ',', ''), '0'), ',');

        return $numero.' '.($anios == 1.0 ? 'año' : 'años');
    }

    private function recortar(string $frase): string
    {
        return Str::limit($frase, 220);
    }

    /** Minúsculas, sin tildes y con espacios simples ("Año" → "ano"). */
    public static function normalizar(string $texto): string
    {
        return Str::of($texto)->ascii()->lower()->squish()->toString();
    }
}

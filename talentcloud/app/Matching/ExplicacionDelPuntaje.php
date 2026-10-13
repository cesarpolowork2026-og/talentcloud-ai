<?php

namespace App\Matching;

use App\Enums\NivelCoincidencia;
use App\Enums\TipoRequisito;
use App\Models\Requisito;
use Illuminate\Support\Collection;

/**
 * Cómo se llega al porcentaje de una postulación, requisito por requisito.
 *
 * El puntaje global es el promedio de los porcentajes ponderado por el peso de cada requisito
 * (igual en el análisis básico y en el servicio de IA), así que cada requisito *aporta*
 * `porcentaje × peso / suma de pesos` puntos sobre un máximo de `100 × peso / suma de pesos`, y
 * lo que aportan todos suma el puntaje. Esta clase solo reordena y redacta lo que el matching ya
 * calculó, con reglas fijas (sin llamar a la IA): ayuda a decidir qué conversar en la entrevista;
 * no califica a la persona ni decide por nadie.
 *
 * @phpstan-type Fila array{id: int, competencia: string, obligatorio: bool, peso: int, anios_minimos: int|null, porcentaje: int, nivel: NivelCoincidencia, aporta: float, maximo: float}
 */
final readonly class ExplicacionDelPuntaje
{
    private const MAXIMO_DE_PREGUNTAS = 5;

    /**
     * @param  list<Fila>  $filas
     */
    public function __construct(public float $score, public array $filas) {}

    /**
     * @param  Collection<int, Requisito>  $requisitos  Los de la vacante, con su competencia cargada.
     */
    public static function de(MatchResult $resultado, Collection $requisitos): self
    {
        $porId = [];
        foreach ($resultado->requisitos as $r) {
            $porId[$r->requisitoId] = $r;
        }

        // Solo cuentan los requisitos vigentes que el matching ya evaluó.
        $evaluados = $requisitos->filter(fn (Requisito $r) => isset($porId[$r->id]));
        $pesoTotal = max(1, (int) $evaluados->sum(fn (Requisito $r) => max(1, $r->peso)));

        $filas = $evaluados->map(function (Requisito $r) use ($porId, $pesoTotal) {
            $match = $porId[$r->id];
            $peso = max(1, $r->peso);

            return [
                'id' => $r->id,
                'competencia' => $match->competencia !== '' ? $match->competencia : (string) $r->competencia?->nombre,
                'obligatorio' => $r->tipo === TipoRequisito::Obligatorio,
                'peso' => $peso,
                'anios_minimos' => $r->anios_minimos,
                'porcentaje' => $match->porcentaje,
                'nivel' => $match->nivel,
                'aporta' => round($match->porcentaje * $peso / $pesoTotal, 1),
                'maximo' => round(100 * $peso / $pesoTotal, 1),
            ];
        })->values()->all();

        return new self($resultado->score, $filas);
    }

    public function total(): int
    {
        return count($this->filas);
    }

    public function conteo(NivelCoincidencia $nivel): int
    {
        return count(array_filter($this->filas, fn (array $f) => $f['nivel'] === $nivel));
    }

    /**
     * Lo que más suma al puntaje y el CV respalda.
     *
     * @return list<Fila>
     */
    public function fortalezas(): array
    {
        $coinciden = array_values(array_filter($this->filas, fn (array $f) => $f['nivel'] === NivelCoincidencia::Coincide));
        usort($coinciden, fn (array $a, array $b) => $b['aporta'] <=> $a['aporta']);

        return array_slice($coinciden, 0, 4);
    }

    /**
     * Lo que no está respaldado: primero los obligatorios, luego lo que más pesa y lo que no aparece antes que lo dudoso.
     *
     * @return list<Fila>
     */
    public function brechas(): array
    {
        $faltan = array_values(array_filter($this->filas, fn (array $f) => $f['nivel'] !== NivelCoincidencia::Coincide));
        usort($faltan, fn (array $a, array $b) => [$b['obligatorio'], $b['peso'], $b['nivel'] === NivelCoincidencia::NoEncontrado] <=> [$a['obligatorio'], $a['peso'], $a['nivel'] === NivelCoincidencia::NoEncontrado]);

        return $faltan;
    }

    /** @return list<string> Competencias obligatorias que el CV no menciona. */
    public function obligatoriosFaltantes(): array
    {
        return array_values(array_map(
            fn (array $f) => $f['competencia'],
            array_filter($this->filas, fn (array $f) => $f['obligatorio'] && $f['nivel'] === NivelCoincidencia::NoEncontrado),
        ));
    }

    /**
     * Preguntas para la entrevista a partir de las brechas, de lo más importante a lo menos.
     *
     * @return list<string>
     */
    public function preguntasSugeridas(): array
    {
        $preguntas = [];

        foreach ($this->brechas() as $f) {
            $c = $f['competencia'];

            $preguntas[] = match (true) {
                $f['nivel'] === NivelCoincidencia::NoEncontrado && $f['obligatorio'] => "{$c} es obligatorio para el puesto y no aparece en el CV. ¿Has trabajado con ello o con algo equivalente? ¿Cómo lo reforzarías en las primeras semanas?",
                $f['nivel'] === NivelCoincidencia::Revisar => "El CV menciona {$c} solo de forma parcial. ¿En qué proyecto lo aplicaste y qué parte hiciste tú?",
                default => "{$c} no aparece en el CV y es deseable. ¿Has tenido contacto con ello, aunque sea fuera del trabajo?",
            };
        }

        // Si hay pocas brechas, se valida lo que el CV sí respalda y el puesto pide con experiencia mínima.
        foreach ($this->filas as $f) {
            if ($f['nivel'] === NivelCoincidencia::Coincide && $f['anios_minimos'] !== null && $f['anios_minimos'] > 0) {
                $preguntas[] = "El puesto pide al menos {$f['anios_minimos']} ".($f['anios_minimos'] === 1 ? 'año' : 'años')." con {$f['competencia']}. ¿Puedes contar un caso reciente que muestre ese nivel?";
            }
        }

        return array_slice($preguntas, 0, self::MAXIMO_DE_PREGUNTAS);
    }
}

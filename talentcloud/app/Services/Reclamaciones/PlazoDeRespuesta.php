<?php

namespace App\Services\Reclamaciones;

use Illuminate\Support\Carbon;

/**
 * Fecha límite para responder una hoja del Libro de Reclamaciones: N días
 * hábiles (config talentcloud.reclamaciones.dias_habiles) contados desde el día
 * siguiente a su presentación, sin sábados, domingos ni feriados nacionales
 * (los fijos de la configuración, más Jueves y Viernes Santo de cada año).
 */
class PlazoDeRespuesta
{
    public function fechaLimite(Carbon $presentada): Carbon
    {
        return $this->sumarDiasHabiles($presentada, (int) config('talentcloud.reclamaciones.dias_habiles'));
    }

    /** El día hábil número $dias contado desde el día siguiente a $desde. */
    public function sumarDiasHabiles(Carbon $desde, int $dias): Carbon
    {
        $dia = $desde->copy()->startOfDay();
        $restantes = $dias;

        while ($restantes > 0) {
            $dia->addDay();

            if ($this->esHabil($dia)) {
                $restantes--;
            }
        }

        return $dia;
    }

    /** Días hábiles que faltan hasta la fecha límite (0 si vence hoy, negativo si ya venció). */
    public function diasHabilesHasta(Carbon $limite): int
    {
        $hoy = today();

        if ($limite->lt($hoy)) {
            return -1 * $this->contar($limite, $hoy);
        }

        return $this->contar($hoy, $limite);
    }

    public function esHabil(Carbon $dia): bool
    {
        return ! $dia->isWeekend() && ! $this->esFeriado($dia);
    }

    private function esFeriado(Carbon $dia): bool
    {
        if (in_array($dia->format('m-d'), (array) config('talentcloud.reclamaciones.feriados'), true)) {
            return true;
        }

        $pascua = $this->domingoDePascua($dia->year);

        return $dia->isSameDay($pascua->copy()->subDays(3)) || $dia->isSameDay($pascua->copy()->subDays(2));
    }

    /** Días hábiles después de $desde y hasta $hasta (inclusive). */
    private function contar(Carbon $desde, Carbon $hasta): int
    {
        $dias = 0;

        for ($dia = $desde->copy()->addDay(); $dia->lte($hasta); $dia->addDay()) {
            if ($this->esHabil($dia)) {
                $dias++;
            }
        }

        return $dias;
    }

    /** Domingo de Pascua (algoritmo de Gauss en su forma "anónima gregoriana"): sin depender de la extensión calendar. */
    private function domingoDePascua(int $anio): Carbon
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($anio, $mes, $dia)->startOfDay();
    }
}

<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/** Formatos de fecha del prototipo ("2 sep", "23 sep · 10:00") y duraciones en días. */
class Formato
{
    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /** "2 sep" (con el año si no es el actual: "2 sep 2025"). */
    public static function fechaCorta(?DateTimeInterface $fecha): string
    {
        if ($fecha === null) {
            return '—';
        }

        $fecha = Carbon::instance($fecha)->timezone(config('app.timezone'));
        $texto = $fecha->day.' '.self::MESES[$fecha->month - 1];

        return $fecha->year === now()->year ? $texto : $texto.' '.$fecha->year;
    }

    /** "23 sep · 10:00". */
    public static function fechaHora(?DateTimeInterface $fecha): string
    {
        if ($fecha === null) {
            return '—';
        }

        $fecha = Carbon::instance($fecha)->timezone(config('app.timezone'));

        return self::fechaCorta($fecha).' · '.$fecha->format('H:i');
    }

    /** "12 días", "1 día", "3,5 días" (un decimal como máximo, coma decimal). */
    public static function dias(float $dias): string
    {
        $redondeado = round($dias, 1);
        $texto = str_replace('.', ',', rtrim(rtrim(number_format($redondeado, 1, '.', ''), '0'), '.'));

        return $texto.' '.($redondeado == 1.0 ? 'día' : 'días');
    }
}

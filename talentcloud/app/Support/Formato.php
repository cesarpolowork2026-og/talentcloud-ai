<?php

namespace App\Support;

use App\Models\Vacante;
use App\Tenancy\TenantContext;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Formatos de fecha del prototipo ("2 sep", "23 sep · 10:00"), duraciones en
 * días y el nombre de una vacante en pantalla.
 */
class Formato
{
    /**
     * "Desarrollador Backend", o "Desarrollador Backend · Nimbus Corp" cuando la
     * vista abarca varias empresas (contexto global: el admin SaaS), para que
     * siempre se sepa de qué empresa es cada vacante o postulante. Cargar
     * vacante.empresa con with() en los listados.
     */
    public static function vacante(?Vacante $vacante): string
    {
        if ($vacante === null) {
            return '';
        }

        if (! app(TenantContext::class)->esGlobal() || $vacante->empresa === null) {
            return $vacante->titulo;
        }

        return $vacante->titulo.' · '.$vacante->empresa->nombre;
    }

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
    /** "23 de octubre de 2026": fechas que se citan en documentos (textos legales). */
    public static function fechaLarga(?DateTimeInterface $fecha): string
    {
        if ($fecha === null) {
            return '—';
        }

        $fecha = Carbon::instance($fecha)->timezone(config('app.timezone'));

        return $fecha->day.' de '.$fecha->locale('es')->monthName.' de '.$fecha->year;
    }

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

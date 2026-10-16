<?php

namespace App\Support\Calendario;

use App\Enums\EstadoEntrevista;
use App\Models\Entrevista;
use App\Support\EnlaceExterno;
use App\Support\Formato;
use Illuminate\Support\Carbon;

/**
 * Una entrevista como evento de calendario (iCalendar, RFC 5545). Los textos
 * escritos por personas se escapan y se pliegan según el estándar para que no
 * puedan abrir una línea nueva del archivo.
 */
final class EventoIcs
{
    /** Para el equipo de selección: con el candidato, el entrevistador y las notas internas. */
    public static function de(Entrevista $entrevista): string
    {
        $postulacion = $entrevista->postulacion;
        $candidato = (string) $postulacion?->candidato?->nombre;
        $vacante = Formato::vacante($postulacion?->vacante);
        $enlace = EnlaceExterno::seguro($entrevista->enlace);

        return self::armar($entrevista, "Entrevista: {$candidato} · {$vacante}", array_filter([
            "Vacante: {$vacante}",
            'Entrevistador: '.$entrevista->entrevistador?->name,
            'Modalidad: '.$entrevista->modalidad->etiqueta(),
            $enlace ? "Enlace: {$enlace}" : null,
            $entrevista->notas ? 'Notas: '.$entrevista->notas : null,
            'Generado por TalentCloud AI.',
        ]), $enlace);
    }

    /**
     * Para el propio candidato: solo lo que ya ve en «Mis postulaciones» (vacante, empresa, modalidad
     * y enlace). Nunca las notas internas ni el nombre del entrevistador.
     */
    public static function paraCandidato(Entrevista $entrevista, string $vacante, string $empresa): string
    {
        $enlace = EnlaceExterno::seguro($entrevista->enlace);

        return self::armar($entrevista, "Entrevista: {$vacante} · {$empresa}", array_filter([
            "Vacante: {$vacante}",
            "Empresa: {$empresa}",
            'Modalidad: '.$entrevista->modalidad->etiqueta(),
            $enlace ? "Enlace: {$enlace}" : null,
            'Generado por TalentCloud AI.',
        ]), $enlace);
    }

    /**
     * @param  array<int, string>  $descripcion
     */
    private static function armar(Entrevista $entrevista, string $resumen, array $descripcion, ?string $enlace): string
    {
        $inicio = Carbon::instance($entrevista->inicia_at)->utc();
        $fin = $inicio->copy()->addMinutes($entrevista->duracion_min);

        $lineas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//TalentCloud AI//Entrevistas//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            "UID:entrevista-{$entrevista->id}@talentcloud.ai",
            'DTSTAMP:'.self::fecha(now()),
            'DTSTART:'.self::fecha($inicio),
            'DTEND:'.self::fecha($fin),
            'SUMMARY:'.self::escapar($resumen),
            'DESCRIPTION:'.self::escapar(implode("\n", $descripcion)),
            'LOCATION:'.self::escapar($enlace ?? $entrevista->modalidad->etiqueta()),
            'STATUS:'.match ($entrevista->estado) {
                EstadoEntrevista::Cancelada => 'CANCELLED',
                EstadoEntrevista::Pendiente => 'TENTATIVE',
                default => 'CONFIRMED',
            },
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map(self::plegar(...), $lineas))."\r\n";
    }

    /** Nombre de archivo seguro: «entrevista-elena-ruiz-2026-10-13.ics». */
    public static function nombreDeArchivo(Entrevista $entrevista): string
    {
        $candidato = str($entrevista->postulacion?->candidato->nombre ?? 'candidato')->slug()->limit(40, '')->toString();

        return "entrevista-{$candidato}-{$entrevista->inicia_at->format('Y-m-d')}.ics";
    }

    private static function fecha(Carbon $fecha): string
    {
        return $fecha->copy()->utc()->format('Ymd\THis\Z');
    }

    /** Texto de usuario: se escapan \ ; , y los saltos de línea (RFC 5545 §3.3.11). */
    private static function escapar(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);

        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $texto);
    }

    /** Las líneas no pasan de 75 octetos: la continuación empieza con un espacio (RFC 5545 §3.1). */
    private static function plegar(string $linea): string
    {
        if (strlen($linea) <= 75) {
            return $linea;
        }

        $partes = [];
        $resto = $linea;
        $limite = 75;

        while (strlen($resto) > $limite) {
            $corte = $limite;
            // No partir un carácter UTF-8 a la mitad.
            while ($corte > 0 && (ord($resto[$corte]) & 0xC0) === 0x80) {
                $corte--;
            }
            $partes[] = substr($resto, 0, $corte);
            $resto = substr($resto, $corte);
            $limite = 74; // la línea siguiente lleva un espacio delante
        }
        $partes[] = $resto;

        return implode("\r\n ", $partes);
    }
}

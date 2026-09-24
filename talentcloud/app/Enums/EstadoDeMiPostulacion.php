<?php

namespace App\Enums;

use App\Models\Postulacion;

/**
 * Estado de una postulación tal como lo ve el postulante ("Mis postulaciones").
 * Traduce la etapa interna: con la vacante cerrada, el proceso se ve "Cerrada"
 * (como en el prototipo) salvo que haya sido contratado; un descarte en un
 * proceso abierto, con el término amable "No seleccionado".
 */
enum EstadoDeMiPostulacion: string
{
    use EsEnumDeEstado;

    case Postulado = 'postulado';
    case EnRevision = 'en_revision';
    case Entrevista = 'entrevista';
    case Oferta = 'oferta';
    case Contratado = 'contratado';
    case NoSeleccionado = 'no_seleccionado';
    case Cerrada = 'cerrada';

    public static function de(Postulacion $postulacion): self
    {
        return match (true) {
            $postulacion->etapa === EtapaPostulacion::Contratado => self::Contratado,
            $postulacion->vacante?->estado === EstadoVacante::Cerrada => self::Cerrada,
            $postulacion->etapa === EtapaPostulacion::Descartado => self::NoSeleccionado,
            default => self::from($postulacion->etapa->value),
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Postulado => 'Postulado',
            self::EnRevision => 'En revisión',
            self::Entrevista => 'Entrevista',
            self::Oferta => 'Oferta',
            self::Contratado => 'Contratado',
            self::NoSeleccionado => 'No seleccionado',
            self::Cerrada => 'Cerrada',
        };
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return match ($this) {
            self::Oferta, self::Contratado => 'accent',
            self::NoSeleccionado, self::Cerrada => 'outline',
            default => 'neutral',
        };
    }
}

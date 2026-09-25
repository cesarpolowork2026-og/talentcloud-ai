<?php

namespace App\Enums;

use App\Models\Postulacion;

/**
 * Estado de una postulación tal como lo ve el postulante ("Mis postulaciones").
 * Traduce la etapa interna: con la vacante cerrada, el proceso se ve "Cerrada"
 * (como en el prototipo) salvo que haya sido contratado; un descarte en un
 * proceso abierto, con el término amable "No seleccionado". Si la retiró el
 * propio postulante, "Retirada" manda sobre todo lo demás.
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
    case Retirada = 'retirada';

    public static function de(Postulacion $postulacion): self
    {
        return match (true) {
            $postulacion->estaRetirada() => self::Retirada,
            $postulacion->etapa === EtapaPostulacion::Contratado => self::Contratado,
            $postulacion->vacante?->estado === EstadoVacante::Cerrada => self::Cerrada,
            $postulacion->etapa === EtapaPostulacion::Descartado => self::NoSeleccionado,
            default => self::from($postulacion->etapa->value),
        };
    }

    /**
     * Estado que se comunica al postulante con un aviso, o null si su situación
     * actual no se anuncia: Postulado (acaba de postular), Entrevista (tiene su
     * propio aviso al programarla) y Oferta (etapa interna: la empresa la
     * comunica en persona). Una retirada no se avisa: la hizo el propio postulante.
     */
    public static function aAvisar(Postulacion $postulacion): ?self
    {
        return match (true) {
            $postulacion->estaRetirada() => null,
            $postulacion->etapa === EtapaPostulacion::Contratado => self::Contratado,
            $postulacion->etapa === EtapaPostulacion::Descartado => self::NoSeleccionado,
            $postulacion->vacante?->estado === EstadoVacante::Cerrada => self::Cerrada,
            $postulacion->etapa === EtapaPostulacion::EnRevision => self::EnRevision,
            default => null,
        };
    }

    /** El postulante puede retirarse mientras su proceso sigue abierto. */
    public function puedeRetirarse(): bool
    {
        return in_array($this, [self::Postulado, self::EnRevision, self::Entrevista, self::Oferta], true);
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
            self::Retirada => 'Retirada',
        };
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return match ($this) {
            self::Oferta, self::Contratado => 'accent',
            self::NoSeleccionado, self::Cerrada, self::Retirada => 'outline',
            default => 'neutral',
        };
    }
}

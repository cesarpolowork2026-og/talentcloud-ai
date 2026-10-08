<?php

namespace App\Enums;

use App\Models\Postulacion;

/**
 * Estado de una postulación tal como lo ve el postulante ("Mis postulaciones").
 * Traduce la etapa interna y coincide siempre con lo que se le avisó
 * (aAvisar): un descarte es "No seleccionado" también si después cierra la
 * vacante; "Cerrada", para quien seguía en el proceso al cerrarse. La Oferta
 * es interna (la empresa la comunica en persona y puede cambiar): hasta que lo
 * contraten o lo descarten, el postulante sigue viendo "Entrevista", la etapa
 * en la que estuvo de verdad. Si la retiró él mismo, "Retirada" manda sobre todo.
 */
enum EstadoDeMiPostulacion: string
{
    use EsEnumDeEstado;

    case Postulado = 'postulado';
    case EnRevision = 'en_revision';
    case Entrevista = 'entrevista';
    case Contratado = 'contratado';
    case NoSeleccionado = 'no_seleccionado';
    case Cerrada = 'cerrada';
    case Retirada = 'retirada';

    public static function de(Postulacion $postulacion): self
    {
        return match (true) {
            $postulacion->estaRetirada() => self::Retirada,
            $postulacion->etapa === EtapaPostulacion::Contratado => self::Contratado,
            $postulacion->etapa === EtapaPostulacion::Descartado => self::NoSeleccionado,
            $postulacion->vacante?->estado === EstadoVacante::Cerrada => self::Cerrada,
            $postulacion->etapa === EtapaPostulacion::Oferta => self::Entrevista,
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

    /** Qué significa para el postulante, en una frase (detalle de "Mis postulaciones"). */
    public function explicacion(): string
    {
        return match ($this) {
            self::Postulado => 'La empresa recibió tu postulación.',
            self::EnRevision => 'La empresa está revisando tu perfil.',
            self::Entrevista => 'Estás en la etapa de entrevistas.',
            self::Contratado => '¡Felicidades! La empresa te eligió para este puesto.',
            self::NoSeleccionado => 'La empresa siguió con otros perfiles. Puedes postular a otras vacantes.',
            self::Cerrada => 'La vacante cerró y el proceso terminó.',
            self::Retirada => 'Retiraste tu postulación: ya no participas en este proceso.',
        };
    }

    /** El postulante puede retirarse mientras su proceso sigue abierto. */
    public function puedeRetirarse(): bool
    {
        return in_array($this, [self::Postulado, self::EnRevision, self::Entrevista], true);
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Postulado => 'Postulado',
            self::EnRevision => 'En revisión',
            self::Entrevista => 'Entrevista',
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
            self::Contratado => 'accent',
            self::NoSeleccionado, self::Cerrada, self::Retirada => 'outline',
            default => 'neutral',
        };
    }
}

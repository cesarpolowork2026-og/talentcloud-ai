<?php

namespace App\Enums;

/** Columna del Kanban de una postulación (en orden). */
enum EtapaPostulacion: string
{
    use EsEnumDeEstado;

    case Postulado = 'postulado';
    case EnRevision = 'en_revision';
    case Entrevista = 'entrevista';
    case Oferta = 'oferta';
    case Contratado = 'contratado';
    case Descartado = 'descartado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Postulado => 'Postulado',
            self::EnRevision => 'En revisión',
            self::Entrevista => 'Entrevista',
            self::Oferta => 'Oferta',
            self::Contratado => 'Contratado',
            self::Descartado => 'Descartado',
        };
    }

    /**
     * Todavía no llegó a la entrevista: al programarle una, lo coherente es
     * moverla a "Entrevista" (lo decide el reclutador al programar).
     */
    public function antesDeEntrevista(): bool
    {
        return in_array($this, [self::Postulado, self::EnRevision], true);
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return 'neutral';
    }
}

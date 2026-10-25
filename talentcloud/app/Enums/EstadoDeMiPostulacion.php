<?php

namespace App\Enums;

use App\Models\Postulacion;

/**
 * Estado de una postulación como lo ve el postulante. Coincide siempre con lo
 * que se le avisó (aAvisar): cada etapa se le comunica, también la Oferta.
 * Si la retiró él mismo, "Retirada" manda sobre todo.
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
            $postulacion->etapa === EtapaPostulacion::Descartado => self::NoSeleccionado,
            $postulacion->vacante?->estado === EstadoVacante::Cerrada => self::Cerrada,
            default => self::from($postulacion->etapa->value),
        };
    }

    /**
     * Estado que se comunica al postulante con un aviso: todos, para que siga cada paso de su proceso. La única
     * excepción es la retirada, que hizo el propio postulante. (Quien postuló por su cuenta no recibe aviso de
     * «Postulado»: nadie recibe aviso de lo que hizo él mismo; ver EnviarAvisos.)
     */
    public static function aAvisar(Postulacion $postulacion): ?self
    {
        return $postulacion->estaRetirada() ? null : self::de($postulacion);
    }

    /** Qué significa para el postulante, en una frase (detalle de "Mis postulaciones"). */
    public function explicacion(): string
    {
        return match ($this) {
            self::Postulado => 'La empresa recibió tu postulación.',
            self::EnRevision => 'La empresa está revisando tu perfil.',
            self::Entrevista => 'Estás en la etapa de entrevistas.',
            self::Oferta => 'La empresa te hizo una oferta para este puesto y se pondrá en contacto contigo para conversar los detalles.',
            self::Contratado => '¡Felicidades! La empresa te eligió para este puesto.',
            self::NoSeleccionado => 'La empresa siguió con otros perfiles. Puedes postular a otras vacantes.',
            self::Cerrada => 'La vacante cerró y el proceso terminó.',
            self::Retirada => 'Retiraste tu postulación: ya no participas en este proceso.',
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
            self::Contratado, self::Oferta => 'accent',
            self::NoSeleccionado, self::Cerrada, self::Retirada => 'outline',
            default => 'neutral',
        };
    }
}

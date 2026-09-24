<?php

namespace App\Enums;

/** Estado de una entrevista. */
enum EstadoEntrevista: string
{
    use EsEnumDeEstado;

    case Pendiente = 'pendiente';
    case Confirmada = 'confirmada';
    case Completada = 'completada';
    case Cancelada = 'cancelada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Confirmada => 'Confirmada',
            self::Completada => 'Completada',
            self::Cancelada => 'Cancelada',
        };
    }

    /** Pendiente → Confirmada. */
    public function puedeConfirmarse(): bool
    {
        return $this === self::Pendiente;
    }

    /** Se da por hecha una entrevista pendiente o confirmada (una vez que ha empezado: lo valida el servicio). */
    public function puedeCompletarse(): bool
    {
        return in_array($this, [self::Pendiente, self::Confirmada], true);
    }

    /** Completada y Cancelada son estados finales. */
    public function puedeCancelarse(): bool
    {
        return in_array($this, [self::Pendiente, self::Confirmada], true);
    }

    /** Ocupa la agenda del entrevistador (para detectar solapamientos). */
    public function ocupaAgenda(): bool
    {
        return $this !== self::Cancelada;
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return match ($this) {
            self::Pendiente => 'neutral',
            self::Confirmada => 'accent',
            self::Completada => 'accent',
            self::Cancelada => 'outline',
        };
    }
}

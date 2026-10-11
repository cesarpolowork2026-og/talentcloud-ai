<?php

namespace App\Enums;

/**
 * Estado de la suscripción pagada en línea de una empresa, tal como la informa
 * la pasarela. Solo una suscripción vigente da el plan pagado.
 */
enum EstadoSuscripcion: string
{
    use EsEnumDeEstado;

    case Activa = 'activa';
    /** Falló el cobro y la pasarela lo está reintentando: el plan se mantiene. */
    case PagoPendiente = 'pago_pendiente';
    /** El primer pago aún no se completó (por ejemplo, falta la verificación 3D Secure). */
    case Incompleta = 'incompleta';
    case Terminada = 'terminada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activa => 'Al día',
            self::PagoPendiente => 'Pago pendiente',
            self::Incompleta => 'Pago sin completar',
            self::Terminada => 'Terminada',
        };
    }

    /** Tono de <x-ui.tag>. */
    public function tono(): string
    {
        return match ($this) {
            self::Activa => 'accent',
            self::PagoPendiente => 'accent-2',
            self::Incompleta, self::Terminada => 'outline',
        };
    }

    /** La empresa tiene el plan que paga (mientras se reintenta un cobro, también). */
    public function esVigente(): bool
    {
        return $this === self::Activa || $this === self::PagoPendiente;
    }
}

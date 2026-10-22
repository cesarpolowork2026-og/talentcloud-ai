<?php

namespace App\Enums;

/** Cómo se realiza una entrevista. */
enum ModalidadEntrevista: string
{
    use EsEnumDeEstado;

    case Videollamada = 'videollamada';
    case Presencial = 'presencial';
    case Telefonica = 'telefonica';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Videollamada => 'Videollamada',
            self::Presencial => 'Presencial',
            self::Telefonica => 'Telefónica',
        };
    }

    /** Solo la videollamada lleva un enlace. */
    public function llevaEnlace(): bool
    {
        return $this === self::Videollamada;
    }

    /** La presencial lleva una dirección y la telefónica un teléfono o una indicación. */
    public function llevaLugar(): bool
    {
        return $this !== self::Videollamada;
    }

    /** Quien va a una entrevista presencial tiene que saber adónde: la dirección es obligatoria. */
    public function exigeLugar(): bool
    {
        return $this === self::Presencial;
    }

    /** Cómo se llama el dato de contacto en pantallas y avisos. */
    public function etiquetaDelLugar(): string
    {
        return match ($this) {
            self::Presencial => 'Dirección',
            self::Telefonica => 'Teléfono o indicación',
            self::Videollamada => 'Enlace',
        };
    }

    /** Ejemplo para el campo del formulario. */
    public function ejemploDelLugar(): string
    {
        return match ($this) {
            self::Presencial => 'Av. Larco 1301, piso 4, Miraflores',
            self::Telefonica => 'Te llamaremos al +51 999 999 999',
            self::Videollamada => 'https://meet.google.com/…',
        };
    }

    /** Tono de <x-ui.tag>, según tagFor() del prototipo. */
    public function tono(): string
    {
        return 'neutral';
    }
}

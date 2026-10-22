<?php

namespace App\Services\Entrevistas;

use App\Enums\ModalidadEntrevista;
use App\Services\Vacantes\OperacionNoPermitida;

/**
 * Qué dato de contacto lleva una entrevista según su modalidad, en un solo lugar para programar y reprogramar: la
 * videollamada lleva un enlace; la presencial, la dirección (obligatoria: quien va tiene que saber adónde); la
 * telefónica, un teléfono o una indicación (opcional). Lo que no corresponde a la modalidad se descarta, así que al
 * cambiar de modalidad no queda un enlace en una entrevista presencial ni una dirección en una videollamada.
 */
final class ContactoDeEntrevista
{
    /**
     * @return array{enlace: ?string, lugar: ?string}
     *
     * @throws OperacionNoPermitida si la entrevista es presencial y no se indicó la dirección
     */
    public static function para(ModalidadEntrevista $modalidad, ?string $enlace, ?string $lugar): array
    {
        $lugar = $lugar === null ? null : trim($lugar);
        $lugar = $lugar === '' ? null : $lugar;

        if ($modalidad->exigeLugar() && $lugar === null) {
            throw new OperacionNoPermitida('Indica la dirección donde será la entrevista presencial.', 'lugar');
        }

        return [
            'enlace' => $modalidad->llevaEnlace() ? $enlace : null,
            'lugar' => $modalidad->llevaLugar() ? $lugar : null,
        ];
    }
}

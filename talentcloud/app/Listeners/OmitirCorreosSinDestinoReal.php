<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;

/**
 * Quita de cada correo las direcciones de los dominios de demostración
 * (config talentcloud.correo.dominios_sin_envio) y, si no queda ninguna, no lo envía.
 */
class OmitirCorreosSinDestinoReal
{
    public function handle(MessageSending $evento): bool
    {
        $mensaje = $evento->message;
        $quedan = 0;

        foreach (['To', 'Cc', 'Bcc'] as $campo) {
            $todas = $mensaje->{'get'.$campo}();
            $reales = array_values(array_filter($todas, fn (Address $a) => ! $this->esDeDemostracion($a->getAddress())));
            $quedan += count($reales);

            if (count($reales) === count($todas)) {
                continue;
            }

            // Sin direcciones reales se quita la cabecera entera (una «Cc:» vacía no es válida).
            $reales === []
                ? $mensaje->getHeaders()->remove($campo)
                : $mensaje->{Str::lower($campo)}(...$reales);
        }

        return $quedan > 0;
    }

    private function esDeDemostracion(string $correo): bool
    {
        $dominio = Str::lower(Str::afterLast($correo, '@'));

        return in_array($dominio, config('talentcloud.correo.dominios_sin_envio', []), true);
    }
}

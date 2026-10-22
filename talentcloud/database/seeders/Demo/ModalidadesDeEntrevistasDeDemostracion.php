<?php

namespace Database\Seeders\Demo;

use App\Enums\EstadoEntrevista;
use App\Enums\ModalidadEntrevista;
use App\Models\Empresa;
use App\Models\Entrevista;

/**
 * Casi todas las entrevistas de la demostración nacen por videollamada, así que las otras dos modalidades nunca se ven.
 * Esto convierte algunas entrevistas que aún no ocurren: una de cada cuatro pasa a ser presencial, en la sede (ficticia)
 * de la empresa, y de las demás, una de cada siete, telefónica. Lo ya ocurrido no se toca. Es idempotente: solo convierte
 * entrevistas que siguen siendo videollamada.
 */
final class ModalidadesDeEntrevistasDeDemostracion
{
    /** Sede de cada empresa de la demostración (direcciones inventadas). */
    public const SEDES = [
        'Nimbus Corp' => 'Av. Javier Prado Este 2310, piso 8, San Borja, Lima',
        'Vela Studio' => 'Calle Berlín 245, oficina 301, Miraflores, Lima',
        'Orbita Labs' => 'Av. Benavides 1560, piso 5, Miraflores, Lima',
        'Ruta Sur Logística' => 'Av. Argentina 3095, Callao',
        'Brío Retail' => 'Av. Larco 1150, piso 3, Miraflores, Lima',
        'Nuna Salud' => 'Av. Arequipa 2450, Lince, Lima',
        'Altura Café' => 'Calle Mayor 410, Barranco, Lima',
    ];

    public const INDICACION_TELEFONICA = 'Te llamaremos al teléfono de tu ficha en el horario acordado';

    /** @return int cuántas entrevistas se convirtieron */
    public static function aplicar(): int
    {
        $convertidas = 0;

        foreach (self::SEDES as $nombre => $sede) {
            $ids = Entrevista::sinFiltroDeEmpresa()
                ->whereIn('empresa_id', Empresa::query()->where('nombre', $nombre)->pluck('id'))
                ->whereIn('estado', [EstadoEntrevista::Pendiente, EstadoEntrevista::Confirmada])
                ->where('inicia_at', '>', now())
                ->where('modalidad', ModalidadEntrevista::Videollamada)
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $id) {
                $cambio = match (true) {
                    $id % 4 === 0 => ['modalidad' => ModalidadEntrevista::Presencial, 'lugar' => $sede],
                    $id % 7 === 0 => ['modalidad' => ModalidadEntrevista::Telefonica, 'lugar' => self::INDICACION_TELEFONICA],
                    default => null,
                };

                if ($cambio !== null) {
                    $convertidas += Entrevista::sinFiltroDeEmpresa()->whereKey($id)->update($cambio + ['enlace' => null]);
                }
            }
        }

        return $convertidas;
    }
}

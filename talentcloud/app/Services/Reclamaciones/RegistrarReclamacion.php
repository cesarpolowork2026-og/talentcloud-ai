<?php

namespace App\Services\Reclamaciones;

use App\DTO\DatosDeReclamacion;
use App\Enums\EstadoReclamacion;
use App\Events\ReclamacionRegistrada;
use App\Models\Reclamacion;
use Illuminate\Support\Facades\DB;

/**
 * Presenta una hoja del Libro de Reclamaciones: número correlativo sin huecos
 * ni repetidos, fecha límite de respuesta y, tras confirmarse, los correos.
 */
class RegistrarReclamacion
{
    /** Clave del bloqueo de PostgreSQL que serializa la numeración. */
    private const BLOQUEO_NUMERACION = 290571;

    public function __construct(private readonly PlazoDeRespuesta $plazo) {}

    public function ejecutar(DatosDeReclamacion $datos): Reclamacion
    {
        $reclamacion = DB::transaction(function () use ($datos): Reclamacion {
            // Dos hojas simultáneas no pueden tomar el mismo número (el bloqueo dura hasta el commit).
            DB::select('select pg_advisory_xact_lock(?)', [self::BLOQUEO_NUMERACION]);

            $reclamacion = new Reclamacion($datos->aAtributos());
            $reclamacion->numero = (int) Reclamacion::query()->max('numero') + 1;
            $reclamacion->estado = EstadoReclamacion::Pendiente;
            $reclamacion->fecha_limite = $this->plazo->fechaLimite(now());
            $reclamacion->save();

            return $reclamacion;
        });

        ReclamacionRegistrada::dispatch($reclamacion);

        return $reclamacion;
    }
}

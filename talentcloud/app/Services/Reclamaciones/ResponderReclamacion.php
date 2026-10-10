<?php

namespace App\Services\Reclamaciones;

use App\Enums\EstadoReclamacion;
use App\Events\ReclamacionRespondida;
use App\Models\Reclamacion;
use App\Models\User;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Support\Facades\DB;

/**
 * Registra la respuesta del proveedor a una hoja: queda guardada (quién y cuándo)
 * y se envía al consumidor. Una hoja se responde una sola vez.
 */
class ResponderReclamacion
{
    public function ejecutar(Reclamacion $reclamacion, string $respuesta, User $admin): Reclamacion
    {
        $fila = DB::transaction(function () use ($reclamacion, $respuesta, $admin): Reclamacion {
            $fila = Reclamacion::query()->lockForUpdate()->findOrFail($reclamacion->id);

            if ($fila->estado === EstadoReclamacion::Respondida) {
                throw new OperacionNoPermitida('La hoja '.$fila->codigo().' ya fue respondida.', 'respuesta');
            }

            $fila->estado = EstadoReclamacion::Respondida;
            $fila->respuesta = trim($respuesta);
            $fila->respondida_at = now();
            $fila->respondida_por = $admin->id;
            $fila->save();

            return $fila;
        });

        ReclamacionRespondida::dispatch($fila);

        return $fila;
    }
}

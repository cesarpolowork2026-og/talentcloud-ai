<?php

namespace App\Services\Postulaciones;

use App\Models\MatchFeedback;
use App\Models\Postulacion;
use App\Models\User;

/**
 * "Marcar como incorrecto": el reclutador señala un resultado de la IA que no
 * comparte. No cambia el matching; queda registrado para recalibrar (día 14).
 */
class MarcarResultadoIncorrecto
{
    /** Marca o desmarca; devuelve true si quedó marcado. */
    public function alternar(Postulacion $postulacion, int $requisitoId, User $usuario): bool
    {
        $existente = MatchFeedback::where('postulacion_id', $postulacion->id)
            ->where('requisito_id', $requisitoId)
            ->where('user_id', $usuario->id)
            ->first();

        if ($existente !== null) {
            $existente->delete();

            return false;
        }

        $marca = new MatchFeedback(['postulacion_id' => $postulacion->id, 'requisito_id' => $requisitoId, 'user_id' => $usuario->id]);
        $marca->empresa_id = $postulacion->empresa_id;
        $marca->save();

        return true;
    }
}

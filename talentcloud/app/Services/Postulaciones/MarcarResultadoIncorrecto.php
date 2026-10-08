<?php

namespace App\Services\Postulaciones;

use App\Models\MatchFeedback;
use App\Models\Postulacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * "Marcar como incorrecto" un resultado del matching (señal para mejorar la
 * IA; no cambia el matching). La intención es explícita: un doble envío deja
 * la marca como se pidió, en lugar de ponerla y quitarla.
 */
class MarcarResultadoIncorrecto
{
    public function marcar(Postulacion $postulacion, int $requisitoId, User $usuario): void
    {
        $marca = new MatchFeedback(['postulacion_id' => $postulacion->id, 'requisito_id' => $requisitoId, 'user_id' => $usuario->id]);
        $marca->empresa_id = $postulacion->empresa_id;

        if ($this->deUsuario($postulacion, $requisitoId, $usuario)->exists()) {
            return;
        }

        try {
            $marca->save();
        } catch (UniqueConstraintViolationException) {
            // Otra petición simultánea ya la registró: el resultado es el pedido.
        }
    }

    public function quitar(Postulacion $postulacion, int $requisitoId, User $usuario): void
    {
        $this->deUsuario($postulacion, $requisitoId, $usuario)->delete();
    }

    /** @return Builder<MatchFeedback> */
    private function deUsuario(Postulacion $postulacion, int $requisitoId, User $usuario): Builder
    {
        return MatchFeedback::where('postulacion_id', $postulacion->id)
            ->where('requisito_id', $requisitoId)
            ->where('user_id', $usuario->id);
    }
}

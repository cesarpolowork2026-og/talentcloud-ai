<?php

namespace App\Services\Postulaciones;

use App\Events\NotaAgregada;
use App\Models\Nota;
use App\Models\Postulacion;
use App\Models\User;
use App\Services\Vacantes\OperacionNoPermitida;

/**
 * Notas internas del equipo sobre una postulación: se añaden y solo su autor
 * las borra. Nunca se muestran al postulante ni a otra empresa.
 */
class NotasDePostulacion
{
    public const MAXIMO_CARACTERES = 2000;

    public function agregar(Postulacion $postulacion, User $autor, string $texto): Nota
    {
        $texto = trim($texto);

        if ($texto === '') {
            throw new OperacionNoPermitida('Escribe la nota.', 'texto');
        }

        if (mb_strlen($texto) > self::MAXIMO_CARACTERES) {
            throw new OperacionNoPermitida('La nota no puede superar '.self::MAXIMO_CARACTERES.' caracteres.', 'texto');
        }

        $nota = new Nota(['texto' => $texto]);
        $nota->postulacion_id = $postulacion->id;
        $nota->empresa_id = $postulacion->empresa_id;
        $nota->autor_id = $autor->id;
        $nota->save();

        NotaAgregada::dispatch($nota);

        return $nota;
    }

    public function borrar(Nota $nota, User $usuario): void
    {
        if ($nota->autor_id !== $usuario->id) {
            throw new OperacionNoPermitida('Solo quien escribió la nota puede borrarla.');
        }

        $nota->delete();
    }
}

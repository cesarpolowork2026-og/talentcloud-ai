<?php

namespace App\Observers;

use App\Models\Postulacion;
use App\Models\PostulacionEtapa;

/**
 * Anota el historial de etapas en el propio modelo, no en cada servicio: así
 * queda registrado por cualquier camino (servicios, Kanban, factories,
 * seeders). Los cambios con update() masivo no pasan por aquí; no se usan
 * para la etapa.
 */
class PostulacionObserver
{
    public function created(Postulacion $postulacion): void
    {
        $this->anotar($postulacion, $postulacion->etapa_cambiada_at ?? $postulacion->created_at);
    }

    public function updated(Postulacion $postulacion): void
    {
        if (! $postulacion->wasChanged('etapa')) {
            return;
        }

        $this->anotar($postulacion, $postulacion->wasChanged('etapa_cambiada_at') ? $postulacion->etapa_cambiada_at : null);
    }

    private function anotar(Postulacion $postulacion, ?\DateTimeInterface $desde): void
    {
        $registro = new PostulacionEtapa([
            'etapa' => $postulacion->etapa,
            'desde' => $desde ?? now(),
        ]);
        $registro->empresa_id = $postulacion->empresa_id;
        $registro->postulacion_id = $postulacion->id;
        $registro->save();
    }
}

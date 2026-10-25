<?php

namespace App\Services\Postulaciones;

use App\Enums\EstadoDeMiPostulacion;
use App\Models\Candidato;
use App\Models\Postulacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * «Quitar de mi lista» del postulante: la postulación deja de verse en «Mis postulaciones», nada más. No se borra (la
 * empresa conserva su historial) y se puede volver a mostrar. Si sigue en curso, antes se retira con RetirarPostulacion
 * (la empresa la ve como «Retirada»): una postulación oculta pero viva dejaría al postulante en un proceso que no ve.
 */
class OcultarPostulaciones
{
    public function __construct(private readonly RetirarPostulacion $retirar) {}

    public function ocultar(Postulacion $postulacion, User $postulante): void
    {
        $postulacion->loadMissing('vacante');

        if (EstadoDeMiPostulacion::de($postulacion)->puedeRetirarse()) {
            $postulacion = $this->retirar->ejecutar($postulacion, $postulante);
        }

        $this->marcar([$postulacion->id], now());
    }

    /**
     * Quita de la lista todas las que ya terminaron (contratado, no seleccionado, cerrada o retirada).
     *
     * @return int cuántas se quitaron
     */
    public function ocultarFinalizadas(User $postulante): int
    {
        $ids = $this->propias($postulante)
            ->whereNull('oculta_para_postulante_at')
            ->with('vacante:id,estado')
            ->get(['id', 'vacante_id', 'etapa', 'retirada_at'])
            ->reject(fn (Postulacion $p) => EstadoDeMiPostulacion::de($p)->puedeRetirarse())
            ->pluck('id')
            ->all();

        $this->marcar($ids, now());

        return count($ids);
    }

    public function mostrar(Postulacion $postulacion): void
    {
        $this->marcar([$postulacion->id], null);
    }

    /** @return Builder<Postulacion> */
    private function propias(User $postulante)
    {
        return Postulacion::sinFiltroDeEmpresa()->where('candidato_id', Candidato::where('user_id', $postulante->id)->value('id') ?? 0);
    }

    /** @param  list<int>  $ids */
    private function marcar(array $ids, ?Carbon $cuando): void
    {
        if ($ids !== []) {
            Postulacion::sinFiltroDeEmpresa()->whereKey($ids)->update(['oculta_para_postulante_at' => $cuando]);
        }
    }
}

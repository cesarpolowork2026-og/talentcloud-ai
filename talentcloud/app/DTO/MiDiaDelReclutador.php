<?php

namespace App\DTO;

use App\Models\Entrevista;
use App\Models\Postulacion;
use Illuminate\Support\Collection;

/**
 * Lo que le toca hoy a un reclutador: sus entrevistas, las evaluaciones que le faltan y la cola de postulaciones por
 * revisar. Las listas son solo las primeras filas; los totales dicen cuántas hay en total.
 */
final readonly class MiDiaDelReclutador
{
    /**
     * @param  Collection<int, Entrevista>  $agenda  Sus entrevistas pendientes o confirmadas desde ahora, la más próxima primero.
     * @param  Collection<int, Entrevista>  $porCerrar  Las suyas que ya terminaron y siguen abiertas, la más antigua primero.
     * @param  Collection<int, Postulacion>  $porEvaluar  Entrevistas que hizo y aún no evaluó.
     * @param  Collection<int, Postulacion>  $porRevisar  Las que llevan más tiempo en «Postulado», con `en_espera_desde` (desde cuándo esperan).
     */
    public function __construct(
        public int $entrevistasHoy,
        public int $entrevistasProximas,
        public int $sinConfirmar,
        public Collection $agenda,
        public int $totalPorCerrar,
        public Collection $porCerrar,
        public int $totalPorEvaluar,
        public Collection $porEvaluar,
        public int $totalPorRevisar,
        public Collection $porRevisar,
    ) {}

    /** ¿No le toca nada? Para decir «todo al día» en lugar de listas vacías. */
    public function estaAlDia(): bool
    {
        return $this->agenda->isEmpty() && $this->totalPorCerrar === 0 && $this->totalPorEvaluar === 0 && $this->totalPorRevisar === 0;
    }
}

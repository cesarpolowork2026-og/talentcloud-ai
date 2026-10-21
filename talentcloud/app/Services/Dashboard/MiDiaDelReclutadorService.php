<?php

namespace App\Services\Dashboard;

use App\DTO\MiDiaDelReclutador;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Repositories\Contracts\EvaluacionRepository;
use App\Services\Entrevistas\ResumenDeAgenda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * «Mi día» del reclutador: lo suyo, no lo de toda la empresa. Sus entrevistas (las que él entrevista), las
 * evaluaciones que le faltan y la cola de postulaciones por revisar, que sí es de la empresa porque las postulaciones
 * no se asignan a una persona. Se calcula en cada visita, sin caché: una lista de trabajo no puede estar desfasada.
 * Cada consulta pasa por el filtro por empresa del contexto vigente.
 */
class MiDiaDelReclutadorService
{
    /** Filas que se muestran de cada lista (el total aparece aparte y la pantalla completa está a un clic). */
    public const FILAS = 5;

    public function __construct(private readonly EvaluacionRepository $evaluaciones) {}

    public function calcular(User $reclutador): MiDiaDelReclutador
    {
        $cifras = Entrevista::query()
            ->where('entrevistador_id', $reclutador->id)
            ->toBase()
            ->selectRaw(
                'COUNT(*) FILTER (WHERE inicia_at BETWEEN ? AND ? AND estado IN (?, ?)) AS hoy,
                COUNT(*) FILTER (WHERE inicia_at >= ? AND inicia_at < ? AND estado IN (?, ?)) AS proximas,
                COUNT(*) FILTER (WHERE inicia_at >= ? AND estado = ?) AS sin_confirmar,
                COUNT(*) FILTER (WHERE estado IN (?, ?) AND '.Entrevista::SQL_TERMINA_AT.' < ?) AS por_cerrar',
                [
                    now()->startOfDay(), now()->endOfDay(), EstadoEntrevista::Pendiente->value, EstadoEntrevista::Confirmada->value,
                    now(), now()->addDays(ResumenDeAgenda::DIAS_PROXIMOS), EstadoEntrevista::Pendiente->value, EstadoEntrevista::Confirmada->value,
                    now(), EstadoEntrevista::Pendiente->value,
                    EstadoEntrevista::Pendiente->value, EstadoEntrevista::Confirmada->value, now(),
                ],
            )
            ->first();

        return new MiDiaDelReclutador(
            entrevistasHoy: (int) ($cifras->hoy ?? 0),
            entrevistasProximas: (int) ($cifras->proximas ?? 0),
            sinConfirmar: (int) ($cifras->sin_confirmar ?? 0),
            agenda: $this->agenda($reclutador),
            totalPorCerrar: (int) ($cifras->por_cerrar ?? 0),
            porCerrar: $this->porCerrar($reclutador),
            totalPorEvaluar: $this->evaluaciones->contarPorEvaluar($reclutador),
            porEvaluar: $this->evaluaciones->postulacionesPorEvaluar($reclutador, '', self::FILAS),
            totalPorRevisar: $this->porRevisar()->count(),
            porRevisar: $this->colaPorRevisar(),
        );
    }

    /**
     * @return Collection<int, Entrevista>
     */
    private function agenda(User $reclutador): Collection
    {
        return Entrevista::query()
            ->with(['postulacion:id,empresa_id,candidato_id,vacante_id', 'postulacion.candidato:id,nombre', 'postulacion.vacante:id,titulo'])
            ->where('entrevistador_id', $reclutador->id)
            // Desde el inicio del día: la de las 09:00 sigue a la vista a las 09:30 por si se alargó.
            ->where('inicia_at', '>=', now()->startOfDay())
            ->whereIn('estado', [EstadoEntrevista::Pendiente, EstadoEntrevista::Confirmada])
            ->orderBy('inicia_at')
            ->limit(self::FILAS)
            ->get();
    }

    /**
     * Sus entrevistas que ya terminaron y siguen abiertas, la más antigua primero: sin darlas por completadas no se
     * pueden evaluar y nada más las señala.
     *
     * @return Collection<int, Entrevista>
     */
    private function porCerrar(User $reclutador): Collection
    {
        return Entrevista::query()
            ->with(['postulacion:id,empresa_id,candidato_id,vacante_id', 'postulacion.candidato:id,nombre', 'postulacion.vacante:id,titulo'])
            ->where('entrevistador_id', $reclutador->id)
            ->porCerrar()
            ->orderBy('inicia_at')
            ->limit(self::FILAS)
            ->get();
    }

    /**
     * Postulaciones que siguen en «Postulado» de vacantes activas, sin retirar: la misma definición de «por revisar»
     * que el resto del dashboard.
     *
     * @return Builder<Postulacion>
     */
    private function porRevisar(): Builder
    {
        return Postulacion::query()
            ->where('etapa', EtapaPostulacion::Postulado)
            ->whereNull('retirada_at')
            ->whereHas('vacante', fn (Builder $q) => $q->whereIn('estado', EstadoVacante::activos()));
    }

    /**
     * Las que más esperan primero. Una postulación nueva no tiene `etapa_cambiada_at`: espera desde que llegó.
     *
     * @return Collection<int, Postulacion>
     */
    private function colaPorRevisar(): Collection
    {
        return $this->porRevisar()
            ->select(['postulaciones.id', 'postulaciones.empresa_id', 'postulaciones.vacante_id', 'postulaciones.candidato_id', 'postulaciones.created_at', 'postulaciones.etapa_cambiada_at'])
            ->selectRaw('COALESCE(postulaciones.etapa_cambiada_at, postulaciones.created_at) AS en_espera_desde')
            ->with(['candidato:id,nombre', 'vacante:id,titulo'])
            ->orderByRaw('COALESCE(postulaciones.etapa_cambiada_at, postulaciones.created_at)')
            ->orderBy('postulaciones.id')
            ->limit(self::FILAS)
            ->get();
    }
}

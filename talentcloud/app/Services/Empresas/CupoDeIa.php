<?php

namespace App\Services\Empresas;

use App\Enums\ReservaDeCupoIa;
use App\Models\Empresa;
use App\Models\Postulacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cupo mensual de matching con IA del plan (PlanEmpresa::cupoIaMensual()).
 * Cuenta postulaciones, no cálculos: cada una ocupa un lugar la primera vez que
 * se evalúa con IA (postulaciones.ia_cupo_at) y recalcularla no consume más. El
 * mes es el calendario de APP_TIMEZONE. Con el cupo agotado, CalcularMatch usa
 * el análisis básico y marca la postulación (ia_sin_cupo).
 */
class CupoDeIa
{
    /**
     * Pide lugar para la postulación. Bloquea la fila de la empresa, como
     * LimitesDelPlan: dos cálculos simultáneos no ocupan a la vez el último lugar.
     */
    public function reservar(Postulacion $postulacion): ReservaDeCupoIa
    {
        if ($postulacion->ia_cupo_at !== null) {
            return ReservaDeCupoIa::YaCubierta;
        }

        return DB::transaction(function () use ($postulacion): ReservaDeCupoIa {
            $empresa = Empresa::query()->lockForUpdate()->findOrFail($postulacion->empresa_id);
            $cupo = $empresa->planVigente()->cupoIaMensual();

            if ($cupo !== null && $this->usadasEsteMes($empresa->id) >= $cupo) {
                return ReservaDeCupoIa::Agotado;
            }

            $marcadas = Postulacion::sinFiltroDeEmpresa()
                ->whereKey($postulacion->id)
                ->whereNull('ia_cupo_at')
                ->update(['ia_cupo_at' => now()]);

            // Otro cálculo de la misma postulación ya la había cubierto.
            return $marcadas === 1 ? ReservaDeCupoIa::Reservada : ReservaDeCupoIa::YaCubierta;
        });
    }

    /** Devuelve el lugar: el servicio de IA no respondió y se usó el análisis básico. */
    public function liberar(Postulacion $postulacion): void
    {
        Postulacion::sinFiltroDeEmpresa()->whereKey($postulacion->id)->update(['ia_cupo_at' => null]);
    }

    public function usadasEsteMes(int $empresaId): int
    {
        return Postulacion::sinFiltroDeEmpresa()
            ->where('empresa_id', $empresaId)
            ->where('ia_cupo_at', '>=', self::inicioDelMes())
            ->count();
    }

    public static function inicioDelMes(): Carbon
    {
        return now()->startOfMonth();
    }

    /** Día en que el cupo vuelve a empezar. */
    public static function renovacion(): Carbon
    {
        return now()->startOfMonth()->addMonth();
    }
}

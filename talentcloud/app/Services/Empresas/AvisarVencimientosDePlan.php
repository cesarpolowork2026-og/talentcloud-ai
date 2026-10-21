<?php

namespace App\Services\Empresas;

use App\Enums\EstadoEmpresa;
use App\Enums\PlanEmpresa;
use App\Events\PlanPorVencer;
use App\Events\PlanVencido;
use App\Models\Empresa;
use Illuminate\Support\Facades\DB;

/**
 * Avisa a la cuenta de cada empresa cuyo plan asignado a mano está por vencer (Empresa::DIAS_DE_AVISO días antes) o
 * acaba de vencer. Cada aviso se manda una sola vez por fecha de vencimiento: se recuerda a qué fecha se refería, así
 * que correr la tarea todos los días no repite el correo y una renovación con otra fecha vuelve a avisar.
 * No cambia el plan (eso lo hace la fecha, Empresa::planVigente()); solo informa.
 */
class AvisarVencimientosDePlan
{
    /** Pasados estos días desde el vencimiento ya no se avisa: sería un correo viejo y confuso. */
    public const DIAS_PARA_AVISAR_VENCIDO = 7;

    /** @return int cuántos avisos se emitieron */
    public function ejecutar(): int
    {
        $candidatas = Empresa::query()
            ->where('estado', EstadoEmpresa::Activa)
            ->where('plan', '!=', PlanEmpresa::Gratis)
            ->whereNotNull('plan_vence_at')
            ->where('plan_vence_at', '<=', now()->addDays(Empresa::DIAS_DE_AVISO))
            ->where('plan_vence_at', '>=', now()->subDays(self::DIAS_PARA_AVISAR_VENCIDO))
            ->pluck('id');

        $avisos = 0;

        foreach ($candidatas as $id) {
            $evento = DB::transaction(fn () => $this->marcar($id));

            if ($evento !== null) {
                $evento::dispatch(Empresa::findOrFail($id));
                $avisos++;
            }
        }

        return $avisos;
    }

    /**
     * Relee la empresa con su fila bloqueada (dos ejecuciones a la vez no avisan dos veces), decide qué aviso toca y lo
     * deja anotado. Devuelve la clase del evento a emitir, o null si no toca avisar.
     *
     * @return class-string<PlanVencido|PlanPorVencer>|null
     */
    private function marcar(int $id): ?string
    {
        $empresa = Empresa::query()->lockForUpdate()->findOrFail($id);

        // Una suscripción en línea o un plan sin fecha propia no se avisan aquí.
        if (! $empresa->tieneVencimientoPropio()) {
            return null;
        }

        if ($empresa->planVencido()) {
            if ($empresa->plan_aviso_vencido_para?->equalTo($empresa->plan_vence_at) === true) {
                return null;
            }

            $empresa->plan_aviso_vencido_para = $empresa->plan_vence_at;
            $empresa->save();

            return PlanVencido::class;
        }

        if ($empresa->plan_aviso_pronto_para?->equalTo($empresa->plan_vence_at) === true) {
            return null;
        }

        $empresa->plan_aviso_pronto_para = $empresa->plan_vence_at;
        $empresa->save();

        return PlanPorVencer::class;
    }
}

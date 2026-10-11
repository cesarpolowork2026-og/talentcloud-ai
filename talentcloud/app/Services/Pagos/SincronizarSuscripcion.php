<?php

namespace App\Services\Pagos;

use App\DTO\SuscripcionData;
use App\Enums\PlanEmpresa;
use App\Events\PlanEmpresaCambiado;
use App\Models\Empresa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Copia en la empresa el estado de su suscripción, recién leído de la pasarela,
 * y le da el plan que corresponde. Es lo único que cambia un plan pagado:
 *
 * - suscripción vigente (al día o con un cobro en reintento) → su plan;
 * - deja de estar vigente (cancelada o sin pago tras los reintentos) → Gratis.
 *   Como cualquier bajada de plan, no borra nada (LimitesDelPlan).
 *
 * Se puede repetir sin efectos: con el mismo estado no cambia nada ni avisa. Un
 * aviso tardío de una suscripción anterior no deshace la actual, y una
 * suscripción que nunca estuvo vigente no toca el plan (un Enterprise aplicado
 * por el admin, por ejemplo).
 */
class SincronizarSuscripcion
{
    public function ejecutar(SuscripcionData $suscripcion): void
    {
        $cambio = DB::transaction(function () use ($suscripcion): ?array {
            // Bloquea la fila: el cambio no se cruza con una verificación de límites en curso.
            $empresa = Empresa::query()->lockForUpdate()->where('pasarela_cliente_id', $suscripcion->clienteId)->first();

            if ($empresa === null) {
                Log::warning('Suscripción de un cliente de la pasarela que no es de ninguna empresa.', ['suscripcion' => $suscripcion->id, 'cliente' => $suscripcion->clienteId]);

                return null;
            }

            $vigente = $suscripcion->estado->esVigente();
            $esOtra = $empresa->pasarela_suscripcion_id !== null && $empresa->pasarela_suscripcion_id !== $suscripcion->id;

            if ($esOtra && ! $vigente) {
                return null;
            }

            if ($esOtra && $empresa->tieneSuscripcionVigente()) {
                // Dos pagos a la vez (dos pestañas): la anterior sigue cobrando y hay que cancelarla y reembolsarla.
                Log::error("{$empresa->nombre} tiene dos suscripciones vigentes: se aplica la más reciente.", ['anterior' => $empresa->pasarela_suscripcion_id, 'nueva' => $suscripcion->id]);
            }

            $estabaVigente = ! $esOtra && $empresa->tieneSuscripcionVigente();
            $anterior = $empresa->plan;
            $plan = match (true) {
                $vigente => $suscripcion->plan,
                $estabaVigente => PlanEmpresa::Gratis,
                default => $anterior,
            };

            $empresa->pasarela_suscripcion_id = $suscripcion->id;
            $empresa->suscripcion_estado = $suscripcion->estado;
            $empresa->suscripcion_periodicidad = $suscripcion->periodicidad;
            $empresa->suscripcion_renueva_at = $suscripcion->renuevaAt;
            $empresa->suscripcion_cancela_al_final = $suscripcion->cancelaAlFinal;

            // contratar() termina la prueba: pagar durante la prueba da el plan pagado desde hoy.
            $cambia = $plan !== $anterior;
            if ($cambia) {
                $empresa->contratar($plan);
            }

            $empresa->save();

            return $cambia ? [$empresa, $anterior] : null;
        });

        if ($cambio !== null) {
            PlanEmpresaCambiado::dispatch($cambio[0], $cambio[1], null);
        }
    }
}

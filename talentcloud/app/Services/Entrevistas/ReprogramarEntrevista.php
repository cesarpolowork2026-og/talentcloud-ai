<?php

namespace App\Services\Entrevistas;

use App\DTO\ReprogramacionData;
use App\Enums\EstadoEntrevista;
use App\Enums\ModalidadEntrevista;
use App\Events\EntrevistaReprogramada;
use App\Models\Entrevista;
use App\Models\User;
use App\Repositories\Contracts\EntrevistaRepository;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Support\Formato;
use Illuminate\Support\Facades\DB;

/**
 * Cambia la fecha, duración, modalidad o enlace de una entrevista pendiente o
 * confirmada, con las mismas reglas que al programarla. Una confirmada vuelve
 * a pendiente (la nueva hora hay que confirmarla). Evita cancelar y crear otra,
 * que enviaba dos avisos al candidato.
 */
class ReprogramarEntrevista
{
    public function __construct(
        private readonly EntrevistaRepository $entrevistas,
        private readonly ReglasDeAgenda $reglas,
    ) {}

    public function ejecutar(Entrevista $entrevista, ReprogramacionData $datos): Entrevista
    {
        if ($datos->iniciaAt->lessThanOrEqualTo(now())) {
            throw new OperacionNoPermitida('La entrevista debe ser en una fecha y hora futuras.', 'iniciaAt');
        }

        [$reprogramada, $fechaAnterior] = DB::transaction(function () use ($entrevista, $datos) {
            // Se relee bloqueada, igual que al cambiar de estado.
            $actual = Entrevista::whereKey($entrevista->id)->lockForUpdate()->firstOrFail();

            if (! $actual->estado->puedeReprogramarse()) {
                throw new OperacionNoPermitida('Una entrevista '.mb_strtolower($actual->estado->etiqueta()).' no se puede reprogramar.');
            }

            $postulacion = $actual->postulacion()->with(['vacante', 'candidato'])->firstOrFail();
            $this->reglas->verificarPostulacion($postulacion);

            // Bloquea al entrevistador: dos cambios de agenda a la vez no pasan ambos la comprobación.
            $entrevistador = User::whereKey($actual->entrevistador_id)->lockForUpdate()->first();
            $this->reglas->verificarEntrevistador($entrevistador, $postulacion);

            if ($this->entrevistas->haySolapamiento($actual->entrevistador_id, $datos->iniciaAt, $datos->terminaAt(), salvoId: $actual->id)) {
                throw new OperacionNoPermitida($entrevistador?->name.' ya tiene una entrevista en ese horario.', 'iniciaAt');
            }

            $fechaAnterior = Formato::fechaHora($actual->inicia_at);

            $actual->update([
                'inicia_at' => $datos->iniciaAt,
                'duracion_min' => $datos->duracionMin,
                'modalidad' => $datos->modalidad,
                'enlace' => $datos->modalidad === ModalidadEntrevista::Videollamada ? $datos->enlace : null,
                'estado' => EstadoEntrevista::Pendiente,
            ]);

            return [$actual, $fechaAnterior];
        });

        EntrevistaReprogramada::dispatch($reprogramada, $fechaAnterior);

        return $reprogramada;
    }
}

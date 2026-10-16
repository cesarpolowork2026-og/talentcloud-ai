<?php

namespace App\Livewire\Concerns;

use App\Enums\EstadoProcesamientoCv;
use App\Models\Cv;
use BackedEnum;

/**
 * Sondeo ligero mientras un CV se analiza. `wire:poll="esperarCv"` lee solo el estado
 * del último CV (una consulta) y no repinta mientras siga en análisis; cuando termina,
 * deja que Livewire pinte una vez con el resultado. Antes, cada sondeo repetía el
 * render completo de la pantalla.
 */
trait EsperaElAnalisisDelCv
{
    /** Candidato dueño del CV que se espera (null si no hay). */
    abstract protected function candidatoDelCv(): ?int;

    public function esperarCv(): void
    {
        $candidatoId = $this->candidatoDelCv();

        $estado = $candidatoId === null
            ? null
            : Cv::query()->where('candidato_id', $candidatoId)->orderByDesc('id')->value('estado_procesamiento');
        $estado = $estado instanceof BackedEnum ? $estado->value : $estado;

        if (in_array($estado, [EstadoProcesamientoCv::Pendiente->value, EstadoProcesamientoCv::Procesando->value], true)) {
            $this->skipRender();
        }
    }
}

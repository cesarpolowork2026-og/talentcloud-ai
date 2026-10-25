<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Pantalla que se pone al día sola con lo que hacen otras personas (`wire:poll.15s.visible="revisarCambios"`).
 * Cada sondeo calcula una firma barata de sus datos (conteo y última modificación de cada tabla) y solo vuelve a
 * pintar si cambió: sin cambios, Livewire no envía HTML.
 *
 * La carga de la página no suma consultas: solo anota cuándo se pintó, y el primer sondeo compara las últimas
 * modificaciones con esa hora (un borrado en esos primeros segundos se ve en el siguiente cambio). Después de
 * cada repintado la firma queda alineada con lo pintado, así que las acciones propias no repintan dos veces.
 */
trait SeActualizaSola
{
    /** Firma de lo pintado ('' hasta el primer sondeo). */
    public string $firmaDeDatos = '';

    /** Cuándo se pintó la página (hora de la aplicación, al segundo). */
    public string $pintadoA = '';

    /**
     * Conteo y última modificación de lo que muestra la pantalla.
     *
     * @return list<array{0: int|string|null, 1: string|null}>
     */
    abstract protected function partesDeLaFirma(): array;

    public function revisarCambios(): void
    {
        $partes = $this->partesDeLaFirma();
        $firma = $this->codificar($partes);

        $cambio = $this->firmaDeDatos === ''
            ? collect($partes)->contains(fn (array $parte) => $parte[1] !== null && (string) $parte[1] >= $this->pintadoA)
            : $firma !== $this->firmaDeDatos;

        $this->firmaDeDatos = $firma;

        if (! $cambio) {
            $this->skipRender();
        }
    }

    /** Después de cada repintado (salvo el de la carga) la firma queda alineada con lo pintado. */
    public function renderedSeActualizaSola(): void
    {
        if ($this->pintadoA === '') {
            $this->pintadoA = now()->format('Y-m-d H:i:s');

            return;
        }

        $this->firmaDeDatos = $this->codificar($this->partesDeLaFirma());
    }

    /**
     * Conteo y última modificación de cada modelo, con el filtro de empresa del contexto (la empresa del usuario o
     * todas para el admin SaaS).
     *
     * @param  list<class-string<Model>>  $modelos
     * @return list<array{0: int|string|null, 1: string|null}>
     */
    protected function partesDeModelos(array $modelos): array
    {
        return array_map(function (string $modelo): array {
            $fila = $modelo::query()->toBase()->selectRaw('COUNT(*) AS n, MAX(updated_at) AS u')->first();

            return [$fila->n ?? 0, $fila->u ?? null];
        }, $modelos);
    }

    /** @param  list<array{0: int|string|null, 1: string|null}>  $partes */
    private function codificar(array $partes): string
    {
        return collect($partes)->map(fn (array $parte) => $parte[0].'@'.$parte[1])->join('|');
    }
}

<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

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
        return array_map(fn (string $modelo): array => $this->parteDe($modelo::query()), $modelos);
    }

    /**
     * Conteo y última modificación de una consulta (con los filtros que ya traiga).
     *
     * @param  EloquentBuilder<*>|Relation<*, *, *>  $consulta
     * @return array{0: int|string|null, 1: string|null}
     */
    protected function parteDe(EloquentBuilder|Relation $consulta, string $columna = 'updated_at'): array
    {
        $tabla = $consulta->getModel()->getTable();
        $fila = $consulta->toBase()->selectRaw("COUNT(*) AS n, MAX({$tabla}.{$columna}) AS u")->first();

        return [$fila->n ?? 0, $fila->u ?? null];
    }

    /**
     * Última modificación de una sola fila, como parte de la firma (null si ya no existe).
     *
     * @param  EloquentBuilder<*>  $consulta
     * @return array{0: int, 1: mixed}
     */
    protected function parteDeFila(EloquentBuilder $consulta, int $id): array
    {
        $modelo = $consulta->getModel();

        return [0, $consulta->toBase()->where($modelo->getTable().'.'.$modelo->getKeyName(), $id)->value($modelo->getTable().'.updated_at')];
    }

    /** @param  list<array{0: int|string|null, 1: string|null}>  $partes */
    private function codificar(array $partes): string
    {
        return collect($partes)->map(fn (array $parte) => $parte[0].'@'.$parte[1])->join('|');
    }
}

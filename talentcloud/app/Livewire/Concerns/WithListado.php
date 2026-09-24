<?php

namespace App\Livewire\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Listado con búsqueda, filtro por estado, orden y paginación: el equivalente
 * de buildList() del prototipo. La vista usa <x-ui.listado-controles> y
 * {{ $filas->links() }}.
 *
 * El componente define qué se busca, qué estados y órdenes existen y cuántas
 * filas por página; los valores que llegan por URL se validan contra esas
 * listas antes de tocar la consulta.
 */
trait WithListado
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $busqueda = '';

    /** Valor del estado filtrado; '' = "Todas". */
    #[Url(except: '')]
    public string $estado = '';

    #[Url(except: '')]
    public string $orden = '';

    #[Url(except: 'asc')]
    public string $direccion = 'asc';

    /**
     * Columnas donde se busca. "relacion.columna" busca en una relación, también
     * anidada ("postulacion.candidato.nombre").
     *
     * @return list<string>
     */
    abstract protected function camposBusqueda(): array;

    /**
     * Estados para los chips de filtro (sin "Todas").
     *
     * @return array<string, string> valor => etiqueta
     */
    protected function opcionesEstado(): array
    {
        return [];
    }

    /** @return array<string, string> clave => etiqueta del selector "Ordenar por" */
    protected function opcionesOrden(): array
    {
        return [];
    }

    protected function columnaEstado(): string
    {
        return 'estado';
    }

    /** Columna (o alias de withCount) por la que se ordena para cada clave. */
    protected function columnaDeOrden(string $clave): string
    {
        return $clave;
    }

    protected function porPagina(): int
    {
        return 10;
    }

    /**
     * Orden vigente: el elegido si es válido; si no (vacío o manipulado en la
     * URL), el primero de opcionesOrden(), que no se escribe en la URL.
     */
    protected function ordenEfectivo(): ?string
    {
        $opciones = $this->opcionesOrden();

        if (array_key_exists($this->orden, $opciones)) {
            return $this->orden;
        }

        $primera = array_key_first($opciones);

        return $primera === null ? null : (string) $primera;
    }

    public function mountWithListado(): void
    {
        if (! array_key_exists($this->orden, $this->opcionesOrden())) {
            $this->orden = '';
        }
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatedOrden(): void
    {
        $this->resetPage();
    }

    public function filtrarEstado(string $estado): void
    {
        $this->estado = $estado;
        $this->resetPage();
    }

    public function alternarDireccion(): void
    {
        $this->direccion = $this->direccion === 'asc' ? 'desc' : 'asc';
        $this->resetPage();
    }

    public function paginationView(): string
    {
        return 'pagination.broadsheet';
    }

    /**
     * Aplica búsqueda, filtro, orden y paginación a la consulta base.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $consulta
     * @return LengthAwarePaginator<int, TModel>
     */
    protected function listar(Builder $consulta): LengthAwarePaginator
    {
        return $this->filtrar($consulta)->paginate($this->porPagina());
    }

    /**
     * Búsqueda, filtro de estado y orden vigentes, sin paginar: lo que se ve en
     * pantalla (la exportación a Excel usa esta misma consulta).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $consulta
     * @return Builder<TModel>
     */
    protected function filtrar(Builder $consulta): Builder
    {
        $termino = trim($this->busqueda);

        if ($termino !== '') {
            $patron = '%'.addcslashes($termino, '%_\\').'%';

            $consulta->where(function (Builder $q) use ($patron): void {
                foreach ($this->camposBusqueda() as $campo) {
                    if (str_contains($campo, '.')) {
                        // "postulacion.candidato.nombre": relación anidada hasta el último punto.
                        $relacion = substr($campo, 0, (int) strrpos($campo, '.'));
                        $columna = substr($campo, (int) strrpos($campo, '.') + 1);
                        $q->orWhereHas($relacion, fn (Builder $r) => $r->where($columna, 'ilike', $patron));
                    } else {
                        $q->orWhere($q->qualifyColumn($campo), 'ilike', $patron);
                    }
                }
            });
        }

        if ($this->estado !== '' && array_key_exists($this->estado, $this->opcionesEstado())) {
            $consulta->where($consulta->qualifyColumn($this->columnaEstado()), $this->estado);
        }

        if (($orden = $this->ordenEfectivo()) !== null) {
            $direccion = $this->direccion === 'desc' ? 'desc' : 'asc';
            $consulta->orderBy($this->columnaDeOrden($orden), $direccion);
        }

        // Desempate estable para que la paginación no repita ni salte filas.
        return $consulta->orderBy($consulta->qualifyColumn('id'));
    }
}

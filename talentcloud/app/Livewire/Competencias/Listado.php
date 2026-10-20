<?php

namespace App\Livewire\Competencias;

use App\Livewire\Concerns\WithListado;
use App\Models\Competencia;
use App\Models\User;
use App\Services\Competencias\CatalogoDeCompetencias;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * «Competencias» del admin SaaS: el catálogo que comparten todas las empresas al definir sus requisitos. Crear,
 * editar (nombre, categoría y sinónimos), fusionar duplicados y eliminar las que nadie usa.
 */
#[Title('Competencias')]
class Listado extends Component
{
    use WithListado;

    public bool $mostrarFormulario = false;

    public bool $mostrarFusion = false;

    public bool $mostrarEliminar = false;

    /** La competencia que se edita, fusiona o elimina; null al crear una nueva. */
    #[Locked]
    public ?int $competenciaId = null;

    public string $nombre = '';

    public string $categoria = '';

    /** Sinónimos separados por comas. */
    public string $sinonimos = '';

    /** La competencia que se conserva al fusionar. */
    public string $destinoId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Competencia::class);
    }

    /** @return list<string> */
    protected function camposBusqueda(): array
    {
        return ['nombre', 'categoria'];
    }

    /** @return array<string, string> */
    protected function opcionesOrden(): array
    {
        return ['nombre' => 'Nombre', 'categoria' => 'Categoría', 'requisitos' => 'Más usadas en vacantes'];
    }

    protected function columnaDeOrden(string $clave): string
    {
        return match ($clave) {
            'categoria' => 'categoria',
            'requisitos' => 'requisitos_count',
            default => 'nombre',
        };
    }

    protected function porPagina(): int
    {
        return 15;
    }

    public function abrirFormulario(?int $id = null): void
    {
        $competencia = $id === null ? null : $this->competencia($id);
        $competencia === null ? $this->authorize('create', Competencia::class) : $this->authorize('update', $competencia);

        $this->resetValidation();
        $this->competenciaId = $competencia?->id;
        $this->nombre = $competencia->nombre ?? '';
        $this->categoria = $competencia->categoria ?? '';
        $this->sinonimos = implode(', ', $competencia->sinonimos ?? []);
        $this->mostrarFormulario = true;
    }

    public function guardar(CatalogoDeCompetencias $catalogo): void
    {
        $competencia = $this->competenciaId === null ? null : $this->competencia($this->competenciaId);
        $competencia === null ? $this->authorize('create', Competencia::class) : $this->authorize('update', $competencia);

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'categoria' => ['nullable', 'string', 'max:60'],
            'sinonimos' => ['nullable', 'string', 'max:500'],
        ], attributes: ['nombre' => 'nombre']);

        $lista = $datos['sinonimos'] === null ? [] : array_map('trim', explode(',', $datos['sinonimos']));

        try {
            $competencia === null
                ? $catalogo->crear($this->usuario(), $datos['nombre'], $datos['categoria'], $lista)
                : $catalogo->actualizar($this->usuario(), $competencia, $datos['nombre'], $datos['categoria'], $lista);
        } catch (OperacionNoPermitida $e) {
            $this->addError($e->campo ?? 'nombre', $e->getMessage());

            return;
        }

        $this->mostrarFormulario = false;
    }

    /**
     * Buscador de la competencia que se conserva al fusionar: hasta 20 resultados. Sin texto, primero las que se
     * parecen a la que se fusiona (comparten alguna palabra de su nombre o sinónimos); con texto, las que lo
     * contienen en su nombre o en un sinónimo, sin tildes ni mayúsculas.
     *
     * @return list<array{id: int, etiqueta: string, detalle: string|null}>
     */
    #[Renderless]
    public function buscarDestinos(string $texto): array
    {
        $origen = $this->competencia((int) $this->competenciaId);
        $this->authorize('update', $origen);

        $clave = Competencia::normalizar($texto);
        $palabras = fn (Competencia $c): array => collect([$c->nombre, ...$c->sinonimos])
            ->flatMap(fn (string $t) => explode(' ', Competencia::normalizar($t)))
            ->filter(fn (string $p) => mb_strlen($p) >= 4)
            ->unique()->values()->all();
        $deOrigen = $palabras($origen);

        $candidatas = Competencia::query()->whereKeyNot($origen->id)->orderBy('nombre')->get()
            ->map(fn (Competencia $c) => [
                'competencia' => $c,
                'parecida' => array_intersect($deOrigen, $palabras($c)) !== [],
                'texto' => collect([$c->nombre, ...$c->sinonimos])->map(fn (string $t) => Competencia::normalizar($t))->implode(' | '),
            ])
            ->when($clave !== '', fn ($lista) => $lista->filter(fn (array $f) => str_contains($f['texto'], $clave)))
            ->sortByDesc('parecida')
            ->take(20);

        return $candidatas->map(fn (array $f) => [
            'id' => $f['competencia']->id,
            'etiqueta' => $f['competencia']->nombre,
            'detalle' => ($f['parecida'] ? 'Parecida · ' : '').($f['competencia']->categoria ?? 'Sin categoría'),
        ])->values()->all();
    }

    public function abrirFusion(int $id): void
    {
        $this->authorize('update', $competencia = $this->competencia($id));

        $this->resetValidation();
        $this->competenciaId = $competencia->id;
        $this->destinoId = '';
        $this->mostrarFusion = true;
    }

    public function fusionar(CatalogoDeCompetencias $catalogo): void
    {
        $origen = $this->competencia((int) $this->competenciaId);
        $this->authorize('update', $origen);

        $this->validate(['destinoId' => ['required', 'integer']], attributes: ['destinoId' => 'competencia que se conserva']);

        try {
            $catalogo->fusionar($this->usuario(), $origen, $this->competencia((int) $this->destinoId));
        } catch (OperacionNoPermitida $e) {
            $this->addError($e->campo ?? 'destinoId', $e->getMessage());

            return;
        }

        $this->mostrarFusion = false;
    }

    public function confirmarEliminar(int $id, CatalogoDeCompetencias $catalogo): void
    {
        $this->authorize('delete', $competencia = $this->competencia($id));

        $this->resetValidation();
        $this->competenciaId = $competencia->id;

        // Una competencia en uso no se elimina: se avisa en la pantalla, sin abrir una confirmación que cerraría el mensaje.
        $usos = $catalogo->usos($competencia);
        if ($usos['requisitos'] > 0 || $usos['candidatos'] > 0) {
            $this->addError('competencia', "{$competencia->nombre} está en uso ({$usos['requisitos']} requisitos y {$usos['candidatos']} anotaciones de candidatos). Fusiónala con otra competencia para no perder esos datos.");

            return;
        }

        $this->mostrarEliminar = true;
    }

    public function eliminar(CatalogoDeCompetencias $catalogo): void
    {
        $competencia = $this->competencia((int) $this->competenciaId);
        $this->authorize('delete', $competencia);

        try {
            $catalogo->eliminar($this->usuario(), $competencia);
        } catch (OperacionNoPermitida $e) {
            $this->addError($e->campo ?? 'competencia', $e->getMessage());

            return;
        }

        $this->mostrarEliminar = false;
    }

    public function render(): View
    {
        $consulta = Competencia::query()
            ->select('competencias.*')
            ->selectSub(DB::table('requisitos')->selectRaw('count(*)')->whereColumn('requisitos.competencia_id', 'competencias.id'), 'requisitos_count')
            ->selectSub(DB::table('candidato_competencia')->selectRaw('count(*)')->whereColumn('candidato_competencia.competencia_id', 'competencias.id'), 'candidatos_count');

        $hayDialogo = $this->mostrarFusion || $this->mostrarEliminar;
        $seleccionada = $this->competenciaId !== null && $hayDialogo ? Competencia::find($this->competenciaId) : null;

        return view('livewire.competencias.listado', [
            'filas' => $this->listar($consulta),
            'total' => Competencia::count(),
            'seleccionada' => $seleccionada,
            'usosDeLaSeleccionada' => $this->mostrarFusion && $seleccionada !== null ? app(CatalogoDeCompetencias::class)->usos($seleccionada) : null,
        ]);
    }

    private function competencia(int $id): Competencia
    {
        return Competencia::findOrFail($id);
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}

<?php

namespace App\Livewire\Postulante;

use App\DTO\CoberturaDeRequisitos;
use App\Enums\EstadoProcesamientoCv;
use App\Enums\Jornada;
use App\Enums\Modalidad;
use App\Livewire\Concerns\SeActualizaSola;
use App\Livewire\Concerns\WithListado;
use App\Models\Candidato;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;
use App\Services\Postulaciones\AnalisisBasicoDeRequisitos;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Vacantes" del postulante (ampliación sobre el prototipo): las vacantes abiertas de todas las empresas activas, con
 * búsqueda, modalidad, lugar y jornada, y su compatibilidad con el CV según el análisis básico (los requisitos que el
 * CV nombra, con sus sinónimos). La compatibilidad ordena y orienta; nunca impide postular: decide la empresa.
 */
#[Title('Vacantes')]
class Vacantes extends Component
{
    use SeActualizaSola;
    use WithListado;

    /** Tope de vacantes que se analizan contra el CV en cada pintado (todas las abiertas, en la práctica). */
    public const MAXIMO_ANALIZADAS = 500;

    public const ORDENES = ['recientes' => 'Más recientes', 'compatibles' => 'Más compatibles con mi CV', 'sueldo' => 'Mayor sueldo'];

    #[Url(as: 'ordenar', except: 'recientes')]
    public string $ordenar = 'recientes';

    #[Url(as: 'lugar', except: '')]
    public string $lugar = '';

    #[Url(as: 'jornada', except: '')]
    public string $jornada = '';

    /** Solo las vacantes cuyos requisitos obligatorios nombra el CV. */
    #[Url(as: 'compatibles', except: false)]
    public bool $soloCompatibles = false;

    public function mount(): void
    {
        $this->authorize('verPublicadas', Vacante::class);
    }

    /** @return list<string> */
    protected function camposBusqueda(): array
    {
        return ['titulo', 'ubicacion', 'empresa.nombre'];
    }

    /**
     * Los chips de filtro son las modalidades.
     *
     * @return array<string, string>
     */
    protected function opcionesEstado(): array
    {
        return Modalidad::opciones();
    }

    protected function columnaEstado(): string
    {
        return 'modalidad';
    }

    protected function porPagina(): int
    {
        return 12;
    }

    public function hayFiltros(): bool
    {
        return trim($this->busqueda) !== '' || $this->estado !== '' || $this->lugar !== '' || $this->jornada !== '' || $this->soloCompatibles;
    }

    public function updatedOrdenar(): void
    {
        $this->resetPage();
    }

    public function updatedLugar(): void
    {
        $this->resetPage();
    }

    public function updatedJornada(): void
    {
        $this->resetPage();
    }

    public function updatedSoloCompatibles(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset('busqueda', 'estado', 'lugar', 'jornada', 'soloCompatibles');
        $this->resetPage();
    }

    /** Vacantes que se publican o cierran y las postulaciones propias (la marca «Ya postulaste»). */
    protected function partesDeLaFirma(): array
    {
        $candidatoId = Candidato::where('user_id', auth()->id())->value('id') ?? 0;

        return [
            $this->parteDe(Vacante::sinFiltroDeEmpresa()),
            $this->parteDe(Empresa::query()),
            $this->parteDe(Postulacion::sinFiltroDeEmpresa()->where('candidato_id', $candidatoId)),
            $this->parteDe(Cv::query()->where('candidato_id', $candidatoId)),
        ];
    }

    public function render(VacanteRepository $vacantes, AnalisisBasicoDeRequisitos $analisis): View
    {
        /** @var User $user */
        $user = auth()->user();
        $cv = $user->candidato?->load(['cvActual' => Cv::ligero()])->cvActual;
        $texto = $cv?->estado_procesamiento === EstadoProcesamientoCv::Procesado
            ? Cv::query()->whereKey($cv->id)->value('texto_extraido')
            : null;

        // vacante_id => retirada (bool): "Ya postulaste" o "Retirada".
        $postuladas = $user->candidato?->postulacionesEnTodasLasEmpresas()->get(['vacante_id', 'retirada_at'])
            ->mapWithKeys(fn (Postulacion $p) => [$p->vacante_id => $p->estaRetirada()])->all() ?? [];

        // La compatibilidad de todas las abiertas (son pocas: decenas). Ordena, filtra y elige las recomendadas.
        $coberturas = $texto === null ? collect() : $this->coberturas($vacantes, $analisis, $texto);
        $ordenCompatible = $coberturas->sortByDesc(fn (CoberturaDeRequisitos $c) => $this->puntaje($c))->keys();

        $consulta = $vacantes->publicadasParaListado()
            ->when($this->lugar !== '', fn (Builder $q) => $q->where('vacantes.ubicacion', $this->lugar))
            ->when(Jornada::tryFrom($this->jornada) !== null, fn (Builder $q) => $q->where('vacantes.jornada', $this->jornada))
            ->when($this->soloCompatibles && $texto !== null, fn (Builder $q) => $q->whereIn('vacantes.id', $coberturas
                ->filter(fn (CoberturaDeRequisitos $c) => $c->total() > 0 && $c->obligatoriosFaltantes() === [])->keys()->all() ?: [0]));

        match (true) {
            $this->ordenar === 'compatibles' && $ordenCompatible->isNotEmpty() => $consulta->orderByRaw(
                'array_position(ARRAY['.$ordenCompatible->map(fn ($id) => (int) $id)->implode(',').']::bigint[], vacantes.id)'
            ),
            $this->ordenar === 'sueldo' => $consulta->orderByRaw('vacantes.sueldo_maximo DESC NULLS LAST')->orderByDesc('vacantes.publicada_at'),
            default => $consulta->latest('publicada_at'),
        };

        $filas = $this->listar($consulta);

        return view('livewire.postulante.vacantes', [
            'filas' => $filas,
            'modalidades' => Modalidad::opciones(),
            'lugares' => $vacantes->publicadas()->setEagerLoads([])->whereNotNull('vacantes.ubicacion')
                ->distinct()->orderBy('vacantes.ubicacion')->pluck('vacantes.ubicacion')
                ->mapWithKeys(fn (string $lugar) => [$lugar => $lugar])->all(),
            'jornadas' => Jornada::opciones(),
            'ordenes' => self::ORDENES,
            'postuladas' => $postuladas,
            'cv' => $cv,
            'conCv' => $texto !== null,
            'coberturas' => $coberturas,
            // Recomendadas: las 3 más compatibles a las que aún no postuló (solo sin filtros, en la primera página).
            'recomendadas' => $this->hayFiltros() || $filas->currentPage() > 1 ? collect() : $ordenCompatible
                ->reject(fn ($id) => array_key_exists($id, $postuladas))
                ->filter(fn ($id) => $coberturas[$id]->cubiertos() > 0)
                ->take(3)
                ->values(),
            'nombresRecomendadas' => $vacantes->publicadas()->whereIn('vacantes.id', $ordenCompatible->take(10)->all())->get(['vacantes.id', 'vacantes.empresa_id', 'vacantes.titulo', 'vacantes.ubicacion', 'vacantes.modalidad'])->keyBy('id'),
        ]);
    }

    /**
     * Cobertura del CV en cada vacante abierta.
     *
     * @return Collection<int, CoberturaDeRequisitos> vacante_id => cobertura
     */
    private function coberturas(VacanteRepository $vacantes, AnalisisBasicoDeRequisitos $analisis, string $texto): Collection
    {
        return $vacantes->publicadas()
            ->setEagerLoads([])
            ->with(['requisitos' => fn ($q) => $q->withoutGlobalScopes()
                ->select(['id', 'vacante_id', 'competencia_id', 'tipo', 'anios_minimos', 'orden'])
                ->with('competencia:id,nombre,sinonimos')
                ->orderBy('orden')])
            ->orderByDesc('vacantes.publicada_at')
            ->limit(self::MAXIMO_ANALIZADAS)
            ->get(['vacantes.id', 'vacantes.publicada_at'])
            ->mapWithKeys(fn (Vacante $v) => [$v->id => $analisis->paraRequisitos($v->requisitos, $texto)]);
    }

    /** Primero las que cumplen todos los obligatorios, luego el porcentaje y la cantidad de requisitos que nombra. */
    private function puntaje(CoberturaDeRequisitos $cobertura): float
    {
        if ($cobertura->total() === 0) {
            return 0;
        }

        return ($cobertura->obligatoriosFaltantes() === [] ? 1000 : 0) + ($cobertura->porcentaje() ?? 0) + $cobertura->cubiertos() / 100;
    }
}

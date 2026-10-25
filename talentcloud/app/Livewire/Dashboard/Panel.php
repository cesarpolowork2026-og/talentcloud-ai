<?php

namespace App\Livewire\Dashboard;

use App\Enums\Rol;
use App\Livewire\Concerns\SeActualizaSola;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Evaluacion;
use App\Models\Invitacion;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;
use App\Services\Dashboard\DashboardService;
use App\Services\Dashboard\EmbudoDeSeleccion;
use App\Services\Dashboard\MiDiaDelReclutadorService;
use App\Services\Dashboard\TendenciaDePostulaciones;
use App\Services\Empresas\LimitesDelPlan;
use App\Services\Plataforma\PanelDePlataforma;
use App\Services\Vacantes\BuscadorDeVacantes;
use App\Support\CacheDeResumenes;
use App\Support\Formato;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard: qué requiere atención hoy, cómo van las vacantes y el embudo de
 * selección. Para el admin SaaS es el "Panel de la plataforma": el negocio
 * (empresas, planes, ingresos), el crecimiento y el estado del sistema; no
 * calcula nada de la parte de selección, que es de cada empresa.
 */
#[Title('Dashboard')]
class Panel extends Component
{
    use SeActualizaSola;

    public bool $mostrarVistaPrevia = false;

    #[Locked]
    public ?int $vacanteSeleccionada = null;

    /** Filtro del embudo: id de una vacante visible, o '' para todas. */
    public string $vacanteDelEmbudo = '';

    /** Periodo en días de la evolución de postulaciones y del embudo (uno de TendenciaDePostulaciones::PERIODOS). */
    #[Url(as: 'periodo', except: 30)]
    public int $periodo = 30;

    public function updatedPeriodo(): void
    {
        $this->periodo = $this->periodoValido();
    }

    private function periodoValido(): int
    {
        return in_array($this->periodo, TendenciaDePostulaciones::PERIODOS, true) ? $this->periodo : 30;
    }

    public function mount(): void
    {
        $this->authorize('viewAny', Vacante::class);
    }

    /**
     * Buscador del embudo: hasta 20 vacantes (no todas las de la empresa en un desplegable)..
     *
     * @return list<array{id: int, etiqueta: string, detalle: string|null}>
     */
    #[Renderless]
    public function buscarVacantes(string $texto, BuscadorDeVacantes $buscador): array
    {
        $this->authorize('viewAny', Vacante::class);

        return $buscador->buscar($texto);
    }

    public function verVistaPrevia(int $id, VacanteRepository $vacantes): void
    {
        $vacante = $vacantes->buscar($id);
        abort_if($vacante === null, 404);
        $this->authorize('view', $vacante);

        $this->vacanteSeleccionada = $vacante->id;
        $this->mostrarVistaPrevia = true;
    }

    /** Todo lo que alimenta el panel de cada rol; el admin SaaS además ve las empresas cliente. */
    protected function partesDeLaFirma(): array
    {
        $modelos = [Postulacion::class, Entrevista::class, Evaluacion::class, Vacante::class, Invitacion::class];

        if (auth()->user()?->tieneRol(Rol::Admin)) {
            $modelos[] = Empresa::class;
        }

        return $this->partesDeModelos($modelos);
    }

    public function render(DashboardService $dashboard, EmbudoDeSeleccion $embudo, VacanteRepository $vacantes, PanelDePlataforma $panel, LimitesDelPlan $limites, TendenciaDePostulaciones $tendencia, MiDiaDelReclutadorService $miDia): View
    {
        // Un periodo que llegue por la dirección y no sea uno de los ofrecidos se trata como el de 30 días.
        $periodo = $this->periodoValido();

        /** @var User $usuario */
        $usuario = auth()->user();
        $esAdmin = $usuario->tieneRol(Rol::Admin);
        // El reclutador ve «Mi día»: lo suyo. Las métricas de gerencia, el embudo y el plan son de la cuenta de empresa.
        $esReclutador = $usuario->tieneRol(Rol::Reclutador);

        // Un id que no es de una vacante visible se trata como "todas".
        $vacanteDelEmbudo = ctype_digit($this->vacanteDelEmbudo) ? $vacantes->buscar((int) $this->vacanteDelEmbudo) : null;

        return view('livewire.dashboard.panel', [
            'plataforma' => $esAdmin ? CacheDeResumenes::recordar('plataforma', fn () => $panel->resumen(), 30) : null,
            'resumen' => $esAdmin ? null : $dashboard->resumen(),
            'periodo' => $periodo,
            'miDia' => $esReclutador ? $miDia->calcular($usuario) : null,
            'tendencia' => $esAdmin || $esReclutador ? null : $tendencia->calcular($periodo),
            'embudo' => $esAdmin || $esReclutador ? null : $embudo->calcular($vacanteDelEmbudo?->id, $periodo),
            'etiquetaDeLaVacanteDelEmbudo' => $vacanteDelEmbudo ? Formato::vacante($vacanteDelEmbudo->loadMissing('empresa:id,nombre')) : '',
            // El plan es de la empresa: el admin SaaS ve la plataforma entera y no tiene uno propio.
            'uso' => $esAdmin || $esReclutador || $usuario->empresa === null ? null : CacheDeResumenes::recordar('uso-del-plan-'.$usuario->empresa->planVigente()->value, fn () => $limites->uso($usuario->empresa), 30),
            'empresa' => $esAdmin ? null : $usuario->empresa,
            'diasSinRevisar' => DashboardService::DIAS_SIN_REVISAR,
            'titulo' => $esAdmin ? 'Panel de la plataforma' : 'Dashboard',
            'subtitulo' => $esAdmin
                ? 'Tus empresas cliente, los ingresos estimados, el crecimiento y el estado del sistema.'
                : ($esReclutador
                    ? 'Lo que te toca hoy en '.$usuario->empresa?->nombre.': tus entrevistas, tus evaluaciones y las postulaciones por revisar.'
                    : 'Resumen de la actividad de selección de '.$usuario->empresa?->nombre.'.'),
            'seleccionada' => $this->vacanteSeleccionada && $this->mostrarVistaPrevia
                ? $vacantes->buscar($this->vacanteSeleccionada)?->load('empresa')->loadCount('postulaciones')
                : null,
        ])->title($esAdmin ? 'Panel' : 'Dashboard');
    }
}

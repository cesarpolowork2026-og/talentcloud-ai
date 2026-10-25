<?php

namespace App\Livewire\Postulante;

use App\Enums\EstadoDeMiPostulacion;
use App\Enums\EstadoEntrevista;
use App\Livewire\Concerns\SeActualizaSola;
use App\Models\Candidato;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Repositories\Contracts\EntrevistaRepository;
use App\Services\Postulaciones\OcultarPostulaciones;
use App\Services\Postulaciones\RetirarPostulacion;
use App\Services\Vacantes\OperacionNoPermitida;
use App\Tenancy\EmpresaScope;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * "Mis postulaciones" del prototipo (isPostulante). El postulante no tiene
 * contexto de empresa: sus postulaciones se leen sin ese filtro, siempre a
 * través de su propio perfil de candidato.
 */
#[Title('Mis postulaciones')]
class MisPostulaciones extends Component
{
    use SeActualizaSola;

    public bool $mostrarDetalle = false;

    public bool $mostrarRetiro = false;

    public bool $mostrarQuitar = false;

    public bool $mostrarQuitarFinalizadas = false;

    /** La lista muestra las que el postulante quitó (para volver a mostrarlas) en lugar de las demás. */
    #[Url(as: 'quitadas', except: false)]
    public bool $verQuitadas = false;

    #[Locked]
    public ?int $postulacionSeleccionada = null;

    public function verDetalle(int $id): void
    {
        abort_unless($this->consulta()?->whereKey($id)->exists(), 404);

        $this->postulacionSeleccionada = $id;
        $this->mostrarDetalle = true;
    }

    public function confirmarRetiro(): void
    {
        $this->authorize('retirar', $this->seleccionada());

        $this->resetErrorBag();
        // Un diálogo a la vez: el del detalle atraparía el foco del de confirmación.
        $this->mostrarDetalle = false;
        $this->mostrarRetiro = true;
    }

    public function retirar(RetirarPostulacion $retirar): void
    {
        $postulacion = $this->seleccionada();
        $this->authorize('retirar', $postulacion);

        $this->mostrarRetiro = false;
        // Vuelve al detalle, que muestra el resultado (estado "Retirada" o el motivo).
        $this->mostrarDetalle = true;

        try {
            /** @var User $usuario */
            $usuario = auth()->user();
            $retirar->ejecutar($postulacion, $usuario);
        } catch (OperacionNoPermitida $e) {
            $this->addError('retiro', $e->getMessage());

            return;
        }

        $this->dispatch('postulacion-retirada');
    }

    /** «Quitar de mi lista»: si la postulación sigue en curso, el diálogo avisa que antes se retira. */
    public function confirmarQuitar(): void
    {
        $this->authorize('ocultar', $this->seleccionada());

        $this->resetErrorBag();
        $this->mostrarDetalle = false;
        $this->mostrarQuitar = true;
    }

    public function quitar(OcultarPostulaciones $ocultar): void
    {
        $postulacion = $this->seleccionada();
        $this->authorize('ocultar', $postulacion);

        $this->mostrarQuitar = false;

        try {
            $ocultar->ocultar($postulacion, $this->postulante());
        } catch (OperacionNoPermitida $e) {
            // No se pudo retirar (p. ej. la vacante cerró en ese momento): vuelve al detalle con el motivo.
            $this->mostrarDetalle = true;
            $this->addError('retiro', $e->getMessage());

            return;
        }

        $this->postulacionSeleccionada = null;
        $this->dispatch('postulaciones-quitadas');
    }

    public function quitarFinalizadas(OcultarPostulaciones $ocultar): void
    {
        $this->mostrarQuitarFinalizadas = false;

        if ($ocultar->ocultarFinalizadas($this->postulante()) > 0) {
            $this->dispatch('postulaciones-quitadas');
        }
    }

    public function volverAMostrar(OcultarPostulaciones $ocultar): void
    {
        $postulacion = $this->seleccionada();
        $this->authorize('ocultar', $postulacion);

        $ocultar->mostrar($postulacion);
        $this->mostrarDetalle = false;
        $this->postulacionSeleccionada = null;
    }

    public function alternarQuitadas(): void
    {
        $this->verQuitadas = ! $this->verQuitadas;
        $this->mostrarDetalle = false;
        $this->postulacionSeleccionada = null;
    }

    public function render(EntrevistaRepository $entrevistasRepo): View
    {
        $postulaciones = $this->postulaciones();
        /** @var User $usuario */
        $usuario = auth()->user();
        $entrevistas = $usuario->candidato ? $entrevistasRepo->delCandidato($usuario->candidato) : collect();

        return view('livewire.postulante.mis-postulaciones', [
            'postulaciones' => $postulaciones,
            'quitadas' => $this->consulta()?->whereNotNull('postulaciones.oculta_para_postulante_at')->count() ?? 0,
            // Las que ya terminaron y siguen en la lista: «Quitar las finalizadas».
            'finalizadas' => $this->verQuitadas ? 0 : $postulaciones->reject(fn (Postulacion $p) => EstadoDeMiPostulacion::de($p)->puedeRetirarse())->count(),
            // Solo la postulación abierta en el detalle se trae completa.
            'seleccionada' => $this->postulacionSeleccionada !== null && ($this->mostrarDetalle || $this->mostrarRetiro || $this->mostrarQuitar) ? $this->seleccionada() : null,
            'entrevistasPorPostulacion' => $entrevistas->groupBy('postulacion_id'),
            // Próximas: pendientes o confirmadas y aún por celebrarse.
            'proximas' => $entrevistas
                ->filter(fn (Entrevista $e) => in_array($e->estado, [EstadoEntrevista::Pendiente, EstadoEntrevista::Confirmada], true) && $e->inicia_at->isFuture())
                ->values(),
        ]);
    }

    private function postulante(): User
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        return $usuario;
    }

    /** La postulación abierta en el detalle, completa; siempre una del propio postulante. */
    private function seleccionada(): Postulacion
    {
        $postulacion = $this->consulta()?->find($this->postulacionSeleccionada);

        abort_if($postulacion === null, 404);

        return $postulacion;
    }

    /**
     * La lista, solo con las columnas que pinta.
     *
     * @return Collection<int, Postulacion>
     */
    private function postulaciones(): Collection
    {
        return $this->consulta()
            ?->select(['postulaciones.id', 'postulaciones.empresa_id', 'postulaciones.vacante_id', 'postulaciones.candidato_id', 'postulaciones.etapa', 'postulaciones.created_at', 'postulaciones.retirada_at', 'postulaciones.estado_avisado', 'postulaciones.oculta_para_postulante_at'])
            ->when(
                $this->verQuitadas,
                fn ($q) => $q->whereNotNull('postulaciones.oculta_para_postulante_at'),
                fn ($q) => $q->whereNull('postulaciones.oculta_para_postulante_at'),
            )
            ->latest()
            ->get() ?? collect();
    }

    /**
     * Lo que la empresa cambia en sus procesos: etapa, entrevistas y cierre de la vacante (sin contexto de empresa:
     * las postulaciones del propio candidato en todas las empresas).
     */
    protected function partesDeLaFirma(): array
    {
        $ids = $this->consulta()?->toBase()->select('postulaciones.id');

        if ($ids === null) {
            return [];
        }

        $postulaciones = Postulacion::query()->withoutGlobalScope(EmpresaScope::class)->whereIn('id', $ids)
            ->toBase()->selectRaw('COUNT(*) AS n, MAX(updated_at) AS u')->first();
        $entrevistas = Entrevista::query()->withoutGlobalScope(EmpresaScope::class)->whereIn('postulacion_id', $ids)
            ->toBase()->selectRaw('COUNT(*) AS n, MAX(updated_at) AS u')->first();
        $vacantes = DB::table('vacantes')->whereIn('id', Postulacion::query()->withoutGlobalScope(EmpresaScope::class)
            ->whereIn('id', $ids)->select('vacante_id'))->max('updated_at');

        return [
            [$postulaciones->n ?? 0, $postulaciones->u ?? null],
            [$entrevistas->n ?? 0, $entrevistas->u ?? null],
            [0, $vacantes],
        ];
    }

    /** @return HasMany<Postulacion, Candidato>|null */
    private function consulta(): ?HasMany
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        return $usuario->candidato?->postulacionesEnTodasLasEmpresas()
            ->with(['vacante' => fn ($q) => $q->withoutGlobalScope(EmpresaScope::class)->with('empresa')]);
    }
}

<?php

namespace App\Livewire\Postulante;

use App\Enums\EstadoEntrevista;
use App\Enums\NivelCoincidencia;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Repositories\Contracts\EntrevistaRepository;
use App\Tenancy\EmpresaScope;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Mis postulaciones" del prototipo (isPostulante). El postulante no tiene
 * contexto de empresa: sus postulaciones se leen sin ese filtro, siempre a
 * través de su propio perfil de candidato.
 */
#[Title('Mis postulaciones')]
class MisPostulaciones extends Component
{
    public bool $mostrarDetalle = false;

    #[Locked]
    public ?int $postulacionSeleccionada = null;

    public function verDetalle(int $id): void
    {
        abort_unless($this->postulaciones()->contains('id', $id), 404);

        $this->postulacionSeleccionada = $id;
        $this->mostrarDetalle = true;
    }

    public function render(EntrevistaRepository $entrevistasRepo): View
    {
        $postulaciones = $this->postulaciones();
        /** @var User $usuario */
        $usuario = auth()->user();
        $entrevistas = $usuario->candidato ? $entrevistasRepo->delCandidato($usuario->candidato) : collect();

        return view('livewire.postulante.mis-postulaciones', [
            'postulaciones' => $postulaciones,
            'seleccionada' => $postulaciones->firstWhere('id', $this->postulacionSeleccionada),
            'entrevistasPorPostulacion' => $entrevistas->groupBy('postulacion_id'),
            // Próximas: pendientes o confirmadas y aún por celebrarse.
            'proximas' => $entrevistas
                ->filter(fn (Entrevista $e) => in_array($e->estado, [EstadoEntrevista::Pendiente, EstadoEntrevista::Confirmada], true) && $e->inicia_at->isFuture())
                ->values(),
        ]);
    }

    /**
     * "La IA identificó 2 de 3 competencias requeridas en tu CV." (null sin matching).
     *
     * @return array{0: int, 1: int}|null
     */
    public static function competenciasIdentificadas(Postulacion $postulacion): ?array
    {
        $requisitos = (array) ($postulacion->match_detalle['requisitos'] ?? []);

        if ($requisitos === []) {
            return null;
        }

        $coinciden = count(array_filter($requisitos, fn ($r) => is_array($r) && ($r['nivel'] ?? null) === NivelCoincidencia::Coincide->value));

        return [$coinciden, count($requisitos)];
    }

    /** @return Collection<int, Postulacion> */
    private function postulaciones(): Collection
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        return $usuario->candidato?->postulacionesEnTodasLasEmpresas()
            ->with(['vacante' => fn ($q) => $q->withoutGlobalScope(EmpresaScope::class)->with('empresa')])
            ->latest()
            ->get() ?? collect();
    }
}

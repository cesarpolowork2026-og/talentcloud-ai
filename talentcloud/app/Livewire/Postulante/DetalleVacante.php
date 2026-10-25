<?php

namespace App\Livewire\Postulante;

use App\Enums\EstadoProcesamientoCv;
use App\Livewire\Concerns\EsperaElAnalisisDelCv;
use App\Livewire\Concerns\SeActualizaSola;
use App\Models\Candidato;
use App\Models\Cv;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;
use App\Services\Postulaciones\PostularmeAVacante;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Detalle de una vacante publicada y "Postular con mi CV" (ampliación sobre el
 * prototipo). La vacante se lee por su id con VacanteRepository::buscarPublicada:
 * si no existe o ya no es pública, 404.
 */
class DetalleVacante extends Component
{
    use EsperaElAnalisisDelCv;
    use SeActualizaSola;

    #[Locked]
    public int $vacanteId;

    public bool $mostrarConfirmacion = false;

    /** Recién postulado en esta visita: muestra la confirmación. */
    public bool $postulado = false;

    /**
     * Subió el CV desde esta página para postular: al quedar listo se abre una vez la confirmación y, si la cierra,
     * se le recuerda que aún no postuló (subir el CV no es postular, y había quien lo creía).
     */
    public bool $cvSubidoAqui = false;

    public bool $confirmacionOfrecida = false;

    public function mount(int $id, VacanteRepository $vacantes): void
    {
        $this->authorize('verPublicadas', Vacante::class);

        // Solo se comprueba aquí: render() la carga una vez, con sus requisitos.
        abort_unless($vacantes->existePublicada($id), 404);

        $this->vacanteId = $id;
    }

    public function confirmar(): void
    {
        $this->authorize('postular', Vacante::class);

        $this->resetErrorBag();
        $this->mostrarConfirmacion = true;
    }

    public function postular(PostularmeAVacante $postularme): void
    {
        $this->authorize('postular', Vacante::class);

        $this->mostrarConfirmacion = false;

        try {
            $postularme->ejecutar($this->usuario(), $this->vacanteId);
        } catch (OperacionNoPermitida $e) {
            $this->addError('postulacion', $e->getMessage());

            return;
        }

        $this->postulado = true;
    }

    /** El CV se subió desde esta misma página: se vuelve a pintar (y a esperar su análisis). */
    #[On('cv-subido')]
    public function cvSubido(): void
    {
        $this->cvSubidoAqui = true;
    }

    /** La vacante (si cierra o cambia) y la propia postulación (si la empresa la mueve). */
    protected function partesDeLaFirma(): array
    {
        return [
            $this->parteDeFila(Vacante::sinFiltroDeEmpresa(), $this->vacanteId),
            $this->parteDe(Postulacion::sinFiltroDeEmpresa()->where('vacante_id', $this->vacanteId)->where('candidato_id', $this->candidatoDelCv() ?? 0)),
        ];
    }

    public function render(VacanteRepository $vacantes): View
    {
        // Null si dejó de ser pública mientras el postulante la miraba: la vista lo explica.
        $vacante = $vacantes->buscarPublicada($this->vacanteId);
        $candidato = $this->usuario()->candidato;
        $suPostulacion = $candidato?->postulacionesEnTodasLasEmpresas()->where('vacante_id', $this->vacanteId)->first(['id', 'retirada_at']);
        $cv = $candidato?->load(['cvActual' => Cv::ligero()])->cvActual;

        if ($this->cvSubidoAqui && ! $this->confirmacionOfrecida && $vacante !== null && $suPostulacion === null
            && $cv?->estado_procesamiento === EstadoProcesamientoCv::Procesado) {
            $this->confirmacionOfrecida = true;
            $this->mostrarConfirmacion = true;
        }

        return view('livewire.postulante.detalle-vacante', [
            'vacante' => $vacante,
            'cv' => $cv,
            'yaPostulo' => $suPostulacion !== null,
            'laRetiro' => $suPostulacion?->estaRetirada() ?? false,
        ])->title($vacante->titulo ?? 'Vacante');
    }

    protected function candidatoDelCv(): ?int
    {
        return Candidato::where('user_id', auth()->id())->value('id');
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}

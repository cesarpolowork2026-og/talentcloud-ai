<?php

namespace App\Livewire\Postulante;

use App\Livewire\Concerns\EsperaElAnalisisDelCv;
use App\Models\Candidato;
use App\Models\Cv;
use App\Models\User;
use App\Services\Cvs\SubirCv;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** Tarjeta "Mi currículo" del postulante (prototipo: isPostulante). */
class MiCurriculo extends Component
{
    use EsperaElAnalisisDelCv;
    use WithFileUploads;

    public bool $mostrarSubida = false;

    /** Solo el botón y el diálogo de subida (en el detalle de una vacante, para postular sin salir). */
    #[Locked]
    public bool $soloSubida = false;

    /** @var TemporaryUploadedFile|null */
    public $archivoCv = null;

    public function subir(SubirCv $cvs): void
    {
        $candidato = $this->candidato();
        $this->authorize('update', $candidato);

        $this->validate([
            'archivoCv' => ['required', 'file', 'mimes:'.implode(',', config('talentcloud.cvs.mimes')), 'max:'.config('talentcloud.cvs.max_kb')],
        ], attributes: ['archivoCv' => 'CV']);

        /** @var User $user */
        $user = auth()->user();

        if ($this->archivoCv instanceof TemporaryUploadedFile) {
            $cvs->ejecutar($candidato, $this->archivoCv, $user);
        }

        $this->reset('archivoCv');
        $this->mostrarSubida = false;

        // Quien espera un CV listo (el detalle de una vacante) vuelve a mirarlo.
        $this->dispatch('cv-subido');
    }

    public function reintentar(SubirCv $cvs): void
    {
        $cv = $this->candidato()->cvActual;
        abort_if($cv === null, 404);
        $this->authorize('reintentar', $cv);

        $cvs->reintentar($cv);
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        // El registro de postulante siempre crea su perfil; sin él, la tarjeta se muestra vacía.
        $candidato = $user->candidato?->load(['cvActual' => Cv::ligero()]);

        return view('livewire.postulante.mi-curriculo', [
            'cv' => $candidato?->cvActual,
        ]);
    }

    protected function candidatoDelCv(): ?int
    {
        return Candidato::where('user_id', auth()->id())->value('id');
    }

    private function candidato(): Candidato
    {
        /** @var User $user */
        $user = auth()->user();

        $candidato = $user->candidato;
        abort_if($candidato === null, 404);

        return $candidato;
    }
}

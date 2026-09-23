<?php

namespace App\Livewire\Postulante;

use App\Enums\FuenteCompetencia;
use App\Models\Candidato;
use App\Models\User;
use App\Services\Cvs\SubirCv;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** Tarjeta "Mi currículo" del postulante (prototipo: isPostulante). */
class MiCurriculo extends Component
{
    use WithFileUploads;

    public bool $mostrarSubida = false;

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
        $candidato = $user->candidato?->load(['cvActual', 'competencias']);

        return view('livewire.postulante.mi-curriculo', [
            'cv' => $candidato?->cvActual,
            'competenciasIa' => $candidato?->competencias
                ->where('pivot.fuente', FuenteCompetencia::Extraccion->value)
                ->unique('id')
                ->count() ?? 0,
        ]);
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

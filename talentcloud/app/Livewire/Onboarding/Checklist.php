<?php

namespace App\Livewire\Onboarding;

use App\Livewire\Concerns\SeActualizaSola;
use App\Models\Empresa;
use App\Models\Invitacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Empresas\ProgresoOnboarding;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/** "Bienvenido a TalentCloud": checklist de 3 pasos de la cuenta de empresa (prototipo: isOnboarding). */
#[Title('Bienvenido a TalentCloud')]
class Checklist extends Component
{
    use SeActualizaSola;

    public function mount(): void
    {
        $this->authorize('update', $this->empresa());
    }

    /** El avance de la puesta en marcha: datos de la empresa, primera vacante e invitaciones. */
    protected function partesDeLaFirma(): array
    {
        $empresaId = auth()->user()?->empresa_id;

        if ($empresaId === null) {
            return [];
        }

        return [$this->parteDeFila(Empresa::query(), $empresaId), $this->parteDe(Vacante::query()), $this->parteDe(Invitacion::query())];
    }

    public function render(ProgresoOnboarding $progreso): View
    {
        $empresa = $this->empresa();
        $estado = $progreso->estado($empresa);

        $pasos = [
            [
                'clave' => 'perfil',
                'titulo' => 'Completa el perfil de tu empresa',
                'descripcion' => 'Datos básicos y usuarios internos.',
                'accion' => 'Completar',
                'url' => route('mi-empresa', ['abrir' => 'datos']),
                'urlHecho' => route('mi-empresa'),
            ],
            [
                'clave' => 'vacante',
                'titulo' => 'Publica tu primera vacante',
                'descripcion' => 'Define el puesto y las competencias requeridas.',
                'accion' => 'Crear vacante',
                'url' => route('vacantes.index', ['nueva' => 1]),
                'urlHecho' => route('vacantes.index'),
            ],
            [
                'clave' => 'invitar',
                'titulo' => 'Invita a tu equipo de reclutamiento',
                'descripcion' => 'Los reclutadores gestionan vacantes y candidatos.',
                'accion' => 'Invitar',
                'url' => route('mi-empresa', ['abrir' => 'invitar']),
                'urlHecho' => route('mi-empresa'),
            ],
        ];

        foreach ($pasos as &$paso) {
            $paso['hecho'] = $estado[$paso['clave']];
        }

        return view('livewire.onboarding.checklist', [
            'empresa' => $empresa,
            'pasos' => $pasos,
            'completados' => $progreso->completados($empresa),
            'terminado' => $progreso->terminado($empresa),
        ]);
    }

    private function empresa(): Empresa
    {
        /** @var User $user */
        $user = auth()->user();

        abort_if($user->empresa === null, 403);

        return $user->empresa;
    }
}

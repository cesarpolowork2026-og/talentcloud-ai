<?php

namespace App\Livewire\Forms;

use App\DTO\ReprogramacionData;
use App\Enums\ModalidadEntrevista;
use App\Livewire\Entrevistas\Listado;
use App\Models\Entrevista;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Formulario "Reprogramar entrevista": parte de los datos actuales de la entrevista. */
class ReprogramacionForm extends Form
{
    public string $iniciaAt = '';

    public string $duracionMin = '45';

    public string $modalidad = 'videollamada';

    public string $enlace = '';

    public string $lugar = '';

    public function cargar(Entrevista $entrevista): void
    {
        $this->resetValidation();
        // En la zona de la aplicación, igual que al programar.
        $this->iniciaAt = $entrevista->inicia_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
        $this->duracionMin = (string) $entrevista->duracion_min;
        $this->modalidad = $entrevista->modalidad->value;
        $this->enlace = (string) $entrevista->enlace;
        $this->lugar = (string) $entrevista->lugar;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'iniciaAt' => ['required', 'date_format:Y-m-d\TH:i'],
            'duracionMin' => ['required', Rule::in(array_keys(Listado::DURACIONES))],
            'modalidad' => ['required', Rule::enum(ModalidadEntrevista::class)],
            'enlace' => ['nullable', 'url:https,http', 'max:500'],
            'lugar' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['iniciaAt' => 'fecha y hora', 'duracionMin' => 'duración'];
    }

    /** Valida y devuelve los datos para el servicio. */
    public function datos(): ReprogramacionData
    {
        $this->validate();

        return new ReprogramacionData(
            iniciaAt: CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->iniciaAt, config('app.timezone')) ?: now()->toImmutable(),
            duracionMin: (int) $this->duracionMin,
            modalidad: ModalidadEntrevista::from($this->modalidad),
            enlace: $this->enlace !== '' ? $this->enlace : null,
            lugar: $this->lugar !== '' ? $this->lugar : null,
        );
    }
}

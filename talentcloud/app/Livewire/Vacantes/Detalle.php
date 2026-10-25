<?php

namespace App\Livewire\Vacantes;

use App\DTO\DatosVacanteData;
use App\DTO\RequisitoData;
use App\Enums\EstadoVacante;
use App\Enums\Jornada;
use App\Enums\Modalidad;
use App\Enums\TipoRequisito;
use App\Livewire\Concerns\SeActualizaSola;
use App\Models\Competencia;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\Vacante;
use App\Repositories\Contracts\VacanteRepository;
use App\Services\Vacantes\ActualizarVacante;
use App\Services\Vacantes\CambiarEstadoVacante;
use App\Services\Vacantes\GestionarRequisitos;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Detalle de una vacante: datos, requisitos y ciclo de vida. El prototipo
 * solo muestra una vista previa; esta pantalla usa sus mismos componentes.
 */
class Detalle extends Component
{
    use SeActualizaSola;

    #[Locked]
    public int $vacanteId;

    // Datos de la vacante.
    public string $titulo = '';

    public string $ubicacion = '';

    public string $modalidad = 'remoto';

    public string $descripcion = '';

    // Condiciones opcionales que se ven en la bolsa pública.
    public string $jornada = '';

    public string $sueldoMinimo = '';

    public string $sueldoMaximo = '';

    // Nuevo requisito / edición.
    public string $competencia = '';

    public string $tipo = 'obligatorio';

    public string $peso = '3';

    public string $aniosMinimos = '';

    public string $detalleRequisito = '';

    public bool $mostrarEdicionRequisito = false;

    public bool $mostrarQuitarRequisito = false;

    #[Locked]
    public ?int $requisitoSeleccionado = null;

    public bool $mostrarCierre = false;

    public function mount(Vacante $vacante): void
    {
        $this->authorize('view', $vacante);

        $this->vacanteId = $vacante->id;
        $this->llenarDatos($vacante);
    }

    public function guardarDatos(ActualizarVacante $actualizar): void
    {
        $vacante = $this->vacante();
        $this->authorize('update', $vacante);

        $datos = $this->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'ubicacion' => ['nullable', 'string', 'max:120', Rule::requiredIf($this->modalidad !== Modalidad::Remoto->value)],
            'modalidad' => ['required', Rule::enum(Modalidad::class)],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'jornada' => ['nullable', Rule::enum(Jornada::class)],
            'sueldoMinimo' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'sueldoMaximo' => ['nullable', 'integer', 'min:1', 'max:1000000', 'gte:sueldoMinimo'],
        ], messages: [
            'ubicacion.required' => 'Indica dónde se trabajará: una vacante presencial o híbrida necesita ubicación.',
            'sueldoMaximo.gte' => 'El sueldo máximo no puede ser menor que el mínimo.',
            'sueldoMinimo.integer' => 'Escribe el sueldo en soles, sin decimales.',
            'sueldoMaximo.integer' => 'Escribe el sueldo en soles, sin decimales.',
        ], attributes: ['titulo' => 'título del puesto', 'sueldoMinimo' => 'sueldo mínimo', 'sueldoMaximo' => 'sueldo máximo']);

        try {
            $actualizar->ejecutar($vacante, new DatosVacanteData(
                titulo: $datos['titulo'],
                descripcion: $datos['descripcion'] ?: null,
                ubicacion: $datos['ubicacion'] ?: null,
                modalidad: Modalidad::from($datos['modalidad']),
                jornada: ($datos['jornada'] ?? '') !== '' ? Jornada::from($datos['jornada']) : null,
                sueldoMinimo: ($datos['sueldoMinimo'] ?? '') !== '' ? (int) $datos['sueldoMinimo'] : null,
                sueldoMaximo: ($datos['sueldoMaximo'] ?? '') !== '' ? (int) $datos['sueldoMaximo'] : null,
            ));
        } catch (OperacionNoPermitida $e) {
            $this->addError($e->campo ?? 'titulo', $e->getMessage());

            return;
        }

        $this->dispatch('vacante-guardada');
        $this->llenarDatos($this->vacante());
    }

    /** Al pasar a remoto el lugar deja de ser una ubicación: se limpia para no arrastrar «Lima». */
    public function updatedModalidad(string $valor): void
    {
        if (Modalidad::tryFrom($valor) === Modalidad::Remoto) {
            $this->reset('ubicacion');
        }

        $this->resetValidation('ubicacion');
    }

    public function cambiarEstado(string $destino, CambiarEstadoVacante $cambiar): void
    {
        $vacante = $this->vacante();
        $this->authorize('cambiarEstado', $vacante);

        $estado = EstadoVacante::tryFrom($destino);
        abort_if($estado === null, 404);

        try {
            $cambiar->ejecutar($vacante, $estado);
        } catch (OperacionNoPermitida $e) {
            $this->addError('estado', $e->getMessage());
        }

        $this->mostrarCierre = false;
    }

    public function agregarRequisito(GestionarRequisitos $requisitos): void
    {
        $vacante = $this->vacante();
        $this->authorize('update', $vacante);

        $this->resetErrorBag('estado');

        try {
            $requisitos->agregar($vacante, $this->datosRequisito());
        } catch (OperacionNoPermitida $e) {
            $this->addError('competencia', $e->getMessage());

            return;
        }

        $this->reset('competencia', 'tipo', 'peso', 'aniosMinimos', 'detalleRequisito');
    }

    public function editarRequisito(int $id): void
    {
        $vacante = $this->vacante();
        $this->authorize('update', $vacante);

        $requisito = $this->requisito($id)->load('competencia');

        $this->resetValidation();
        $this->requisitoSeleccionado = $requisito->id;
        $this->competencia = $requisito->competencia->nombre;
        $this->tipo = $requisito->tipo->value;
        $this->peso = (string) $requisito->peso;
        $this->aniosMinimos = (string) $requisito->anios_minimos;
        $this->detalleRequisito = (string) $requisito->descripcion;
        $this->mostrarEdicionRequisito = true;
    }

    public function guardarRequisito(GestionarRequisitos $requisitos): void
    {
        $vacante = $this->vacante();
        $this->authorize('update', $vacante);

        try {
            $requisitos->actualizar($vacante, $this->requisito((int) $this->requisitoSeleccionado), $this->datosRequisito());
        } catch (OperacionNoPermitida $e) {
            $this->addError('competencia', $e->getMessage());

            return;
        }

        $this->mostrarEdicionRequisito = false;
        $this->reset('competencia', 'tipo', 'peso', 'aniosMinimos', 'detalleRequisito', 'requisitoSeleccionado');
    }

    public function cancelarEdicion(): void
    {
        $this->mostrarEdicionRequisito = false;
        $this->resetValidation();
        $this->reset('competencia', 'tipo', 'peso', 'aniosMinimos', 'detalleRequisito', 'requisitoSeleccionado');
    }

    public function confirmarQuitarRequisito(int $id): void
    {
        $this->authorize('update', $this->vacante());

        $this->requisitoSeleccionado = $this->requisito($id)->id;
        $this->mostrarQuitarRequisito = true;
    }

    public function quitarRequisito(GestionarRequisitos $requisitos): void
    {
        $vacante = $this->vacante();
        $this->authorize('update', $vacante);

        try {
            $requisitos->quitar($vacante, $this->requisito((int) $this->requisitoSeleccionado));
        } catch (OperacionNoPermitida $e) {
            $this->addError('estado', $e->getMessage());
        }

        $this->mostrarQuitarRequisito = false;
        $this->requisitoSeleccionado = null;
    }

    /** La vacante (estado y datos), sus requisitos y sus postulaciones, que cambian otras personas del equipo. */
    protected function partesDeLaFirma(): array
    {
        return [
            $this->parteDeFila(Vacante::query(), $this->vacanteId),
            $this->parteDe(Requisito::query()->where('vacante_id', $this->vacanteId)),
            $this->parteDe(Postulacion::query()->where('vacante_id', $this->vacanteId)),
        ];
    }

    public function render(VacanteRepository $vacantes): View
    {
        $vacante = $vacantes->conDetalle($this->vacante());
        $seleccionado = $this->requisitoSeleccionado
            ? $vacante->requisitos->firstWhere('id', $this->requisitoSeleccionado)
            : null;

        return view('livewire.vacantes.detalle', [
            'vacante' => $vacante,
            'editable' => $vacante->estado->esEditable() && auth()->user()?->can('update', $vacante),
            'transiciones' => $vacante->estado->transiciones(),
            'catalogo' => Competencia::nombres(),
            'tipos' => TipoRequisito::opciones(),
            'modalidades' => Modalidad::opciones(),
            'jornadas' => Jornada::opciones(),
            'pesos' => [1 => '1 · bajo', 2 => '2', 3 => '3 · medio', 4 => '4', 5 => '5 · alto'],
            'requisitoSeleccionado' => $seleccionado,
        ])->title($vacante->titulo);
    }

    private function datosRequisito(): RequisitoData
    {
        $datos = $this->validate([
            'competencia' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::enum(TipoRequisito::class)],
            'peso' => ['required', 'integer', 'between:1,5'],
            'aniosMinimos' => ['nullable', 'integer', 'between:0,40'],
            'detalleRequisito' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'aniosMinimos' => 'años mínimos',
            'detalleRequisito' => 'detalle',
        ]);

        return new RequisitoData(
            competencia: trim($datos['competencia']),
            tipo: TipoRequisito::from($datos['tipo']),
            peso: (int) $datos['peso'],
            aniosMinimos: $datos['aniosMinimos'] === null || $datos['aniosMinimos'] === '' ? null : (int) $datos['aniosMinimos'],
            descripcion: $datos['detalleRequisito'] ?: null,
        );
    }

    private function llenarDatos(Vacante $vacante): void
    {
        $this->titulo = $vacante->titulo;
        $this->ubicacion = (string) $vacante->ubicacion;
        $this->modalidad = $vacante->modalidad->value;
        $this->descripcion = (string) $vacante->descripcion;
        $this->jornada = (string) $vacante->jornada?->value;
        $this->sueldoMinimo = (string) $vacante->sueldo_minimo;
        $this->sueldoMaximo = (string) $vacante->sueldo_maximo;
    }

    /** Con el filtro por empresa, una vacante de otra empresa no se encuentra. */
    private function vacante(): Vacante
    {
        $vacante = app(VacanteRepository::class)->buscar($this->vacanteId);

        abort_if($vacante === null, 404);

        return $vacante;
    }

    private function requisito(int $id): Requisito
    {
        $requisito = app(VacanteRepository::class)->buscarRequisito($this->vacante(), $id);

        abort_if($requisito === null, 404);

        return $requisito;
    }
}

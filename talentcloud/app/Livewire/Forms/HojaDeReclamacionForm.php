<?php

namespace App\Livewire\Forms;

use App\DTO\DatosDeReclamacion;
use App\Enums\PlanEmpresa;
use App\Enums\TipoDocumento;
use App\Enums\TipoReclamacion;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Hoja del Libro de Reclamaciones con los campos del reglamento: consumidor (y su
 * padre, madre o apoderado si es menor de edad), servicio contratado y detalle.
 */
class HojaDeReclamacionForm extends Form
{
    public string $tipo = 'reclamo';

    public string $nombre = '';

    public string $tipoDocumento = 'dni';

    public string $numeroDocumento = '';

    public string $domicilio = '';

    public string $telefono = '';

    public string $email = '';

    public bool $esMenor = false;

    public string $apoderadoNombre = '';

    public string $apoderadoTipoDocumento = 'dni';

    public string $apoderadoNumeroDocumento = '';

    public string $servicio = '';

    public string $monto = '';

    public string $detalle = '';

    public string $pedido = '';

    public bool $acepta = false;

    /**
     * Servicios que se pueden reclamar: cada plan y la bolsa de empleos (postulantes).
     *
     * @return array<string, string>
     */
    public static function servicios(): array
    {
        $servicios = [];
        foreach (PlanEmpresa::cases() as $plan) {
            $servicios['Plan '.$plan->etiqueta()] = 'Plan '.$plan->etiqueta().' (empresas)';
        }

        return $servicios + [
            'Bolsa de empleos y postulaciones' => 'Bolsa de empleos y postulaciones (postulantes)',
            'Otro' => 'Otro',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoReclamacion::class)],
            'nombre' => ['required', 'string', 'max:120'],
            'tipoDocumento' => ['required', Rule::enum(TipoDocumento::class)],
            'numeroDocumento' => ['required', 'string', $this->formatoDeDocumento($this->tipoDocumento)],
            'domicilio' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'regex:/^[0-9 +()\-]{6,20}$/'],
            'email' => ['required', 'email', 'max:255'],
            'esMenor' => ['boolean'],
            'apoderadoNombre' => ['exclude_unless:esMenor,true', 'required', 'string', 'max:120'],
            'apoderadoTipoDocumento' => ['exclude_unless:esMenor,true', 'required', Rule::enum(TipoDocumento::class)],
            'apoderadoNumeroDocumento' => ['exclude_unless:esMenor,true', 'required', 'string', $this->formatoDeDocumento($this->apoderadoTipoDocumento)],
            'servicio' => ['required', Rule::in(array_keys(self::servicios()))],
            'monto' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'detalle' => ['required', 'string', 'min:10', 'max:3000'],
            'pedido' => ['required', 'string', 'min:5', 'max:2000'],
            'acepta' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'tipo' => 'tipo',
            'nombre' => 'nombre completo',
            'tipoDocumento' => 'tipo de documento',
            'numeroDocumento' => 'número de documento',
            'domicilio' => 'domicilio',
            'telefono' => 'teléfono',
            'email' => 'correo electrónico',
            'apoderadoNombre' => 'nombre del padre, madre o apoderado',
            'apoderadoTipoDocumento' => 'tipo de documento del apoderado',
            'apoderadoNumeroDocumento' => 'número de documento del apoderado',
            'servicio' => 'servicio contratado',
            'monto' => 'monto reclamado',
            'detalle' => 'detalle',
            'pedido' => 'pedido',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'acepta.accepted' => 'Debes confirmar que los datos son verdaderos y que leíste la política de privacidad.',
            'telefono.regex' => 'Escribe solo números (puedes usar +, espacios, guiones y paréntesis).',
        ];
    }

    public function datos(): DatosDeReclamacion
    {
        return new DatosDeReclamacion(
            tipo: TipoReclamacion::from($this->tipo),
            nombre: trim($this->nombre),
            tipoDocumento: TipoDocumento::from($this->tipoDocumento),
            numeroDocumento: mb_strtoupper(trim($this->numeroDocumento)),
            domicilio: trim($this->domicilio),
            telefono: trim($this->telefono) ?: null,
            email: trim($this->email),
            esMenor: $this->esMenor,
            apoderadoNombre: trim($this->apoderadoNombre) ?: null,
            apoderadoTipoDocumento: $this->esMenor ? TipoDocumento::from($this->apoderadoTipoDocumento) : null,
            apoderadoNumeroDocumento: mb_strtoupper(trim($this->apoderadoNumeroDocumento)) ?: null,
            servicio: $this->servicio,
            monto: $this->monto === '' ? null : number_format((float) $this->monto, 2, '.', ''),
            detalle: trim($this->detalle),
            pedido: trim($this->pedido),
        );
    }

    /** El número debe tener el formato del tipo de documento elegido (DNI: 8 dígitos). */
    private function formatoDeDocumento(string $tipo): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) use ($tipo): void {
            $documento = TipoDocumento::tryFrom($tipo);

            if ($documento !== null && ! preg_match($documento->patron(), trim((string) $valor))) {
                $fallar($documento === TipoDocumento::Dni
                    ? 'El DNI debe tener 8 dígitos.'
                    : 'El número de documento debe tener de 6 a 12 letras o dígitos.');
            }
        };
    }
}

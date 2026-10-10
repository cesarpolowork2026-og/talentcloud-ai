<?php

namespace App\DTO;

use App\Enums\TipoDocumento;
use App\Enums\TipoReclamacion;

/** Lo que el consumidor escribe en la hoja del Libro de Reclamaciones (ya validado). */
final readonly class DatosDeReclamacion
{
    public function __construct(
        public TipoReclamacion $tipo,
        public string $nombre,
        public TipoDocumento $tipoDocumento,
        public string $numeroDocumento,
        public string $domicilio,
        public ?string $telefono,
        public string $email,
        public bool $esMenor,
        public ?string $apoderadoNombre,
        public ?TipoDocumento $apoderadoTipoDocumento,
        public ?string $apoderadoNumeroDocumento,
        public string $servicio,
        public ?string $monto,
        public string $detalle,
        public string $pedido,
    ) {}

    /**
     * @return array<string, mixed> atributos del modelo Reclamacion
     */
    public function aAtributos(): array
    {
        return [
            'tipo' => $this->tipo,
            'nombre' => $this->nombre,
            'tipo_documento' => $this->tipoDocumento,
            'numero_documento' => $this->numeroDocumento,
            'domicilio' => $this->domicilio,
            'telefono' => $this->telefono,
            'email' => mb_strtolower(trim($this->email)),
            'es_menor' => $this->esMenor,
            // Sin menor de edad no se guardan datos de apoderado aunque hayan llegado.
            'apoderado_nombre' => $this->esMenor ? $this->apoderadoNombre : null,
            'apoderado_tipo_documento' => $this->esMenor ? $this->apoderadoTipoDocumento : null,
            'apoderado_numero_documento' => $this->esMenor ? $this->apoderadoNumeroDocumento : null,
            'servicio' => $this->servicio,
            'monto' => $this->monto,
            'detalle' => $this->detalle,
            'pedido' => $this->pedido,
        ];
    }
}

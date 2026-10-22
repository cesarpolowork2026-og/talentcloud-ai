<?php

namespace App\Http\Resources;

use App\Models\Vacante;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación pública de una vacante. Lista blanca de campos: nada interno (creador, postulaciones, embeddings).
 * El detalle añade la descripción; el listado no la lee (es texto largo) y la omite.
 *
 * @mixin Vacante
 */
class VacantePublicaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'modalidad' => $this->modalidad->value,
            'ubicacion' => $this->modalidad->normalizarUbicacion($this->ubicacion),
            'jornada' => $this->jornada?->value,
            'sueldo' => [
                'minimo' => $this->sueldo_minimo,
                'maximo' => $this->sueldo_maximo,
                'moneda' => 'PEN',
                'periodo' => 'mes',
                'texto' => $this->sueldoEnTexto(),
            ],
            'publicada_en' => $this->publicada_at?->toIso8601String(),
            'empresa' => $this->whenLoaded('empresa', fn () => [
                'nombre' => $this->empresa->nombre,
                'sector' => $this->empresa->sector,
                'sitio_web' => $this->empresa->sitio_web,
            ]),
            'descripcion' => $this->when(array_key_exists('descripcion', $this->resource->getAttributes()), fn () => $this->descripcion),
            'requisitos' => $this->whenLoaded('requisitos', fn () => $this->requisitos->map(fn ($r) => [
                'competencia' => $r->competencia->nombre,
                'tipo' => $r->tipo->value,
                'anios_minimos' => $r->anios_minimos,
            ])->values()),
            'url' => route('empleos.show', $this->id),
        ];
    }
}

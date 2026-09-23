<?php

namespace App\Matching;

use App\Enums\TipoRequisito;

/** Un requisito de la vacante, tal como lo recibe el motor de matching. */
final readonly class RequisitoInput
{
    /**
     * @param  list<string>  $sinonimos
     */
    public function __construct(
        public int $id,
        public string $competencia,
        public array $sinonimos,
        public TipoRequisito $tipo,
        public int $peso,
        public ?int $aniosMinimos,
        public ?string $descripcion,
    ) {}

    /**
     * @return array{id: int, competencia: string, sinonimos: list<string>, tipo: string, peso: int, anios_minimos: int|null, descripcion: string|null}
     */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'competencia' => $this->competencia,
            'sinonimos' => $this->sinonimos,
            'tipo' => $this->tipo->value,
            'peso' => $this->peso,
            'anios_minimos' => $this->aniosMinimos,
            'descripcion' => $this->descripcion,
        ];
    }
}

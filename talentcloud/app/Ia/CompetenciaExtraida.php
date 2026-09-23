<?php

namespace App\Ia;

/** Competencia del catálogo encontrada en el CV, con los años que acredita y su evidencia. */
final readonly class CompetenciaExtraida
{
    public function __construct(
        public int $competenciaId,
        public string $nombre,
        public ?float $anios,
        public string $evidencia,
        public string $seccion,
        // Solo mencionada como interés o nivel básico: no acredita la competencia.
        public bool $dudosa,
    ) {}

    public static function desde(Campos $campos): self
    {
        return new self(
            competenciaId: $campos->entero('competencia_id'),
            nombre: $campos->texto('nombre'),
            anios: $campos->decimalOpcional('anios'),
            evidencia: $campos->texto('evidencia'),
            seccion: $campos->texto('seccion'),
            dudosa: $campos->booleano('dudosa'),
        );
    }

    /**
     * @return array{competencia_id: int, nombre: string, anios: float|null, evidencia: string, seccion: string, dudosa: bool}
     */
    public function aArreglo(): array
    {
        return [
            'competencia_id' => $this->competenciaId,
            'nombre' => $this->nombre,
            'anios' => $this->anios,
            'evidencia' => $this->evidencia,
            'seccion' => $this->seccion,
            'dudosa' => $this->dudosa,
        ];
    }
}

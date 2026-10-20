<?php

namespace App\DTO;

use App\Enums\TipoRequisito;
use App\Models\Requisito;

/**
 * Qué requisitos de una vacante cubre un candidato según el análisis básico (sin IA): el CV nombra la competencia o
 * su empresa la anotó en su ficha. Es una ayuda para revisar, no un veredicto: nunca mueve ni descarta a nadie.
 */
final readonly class CoberturaDeRequisitos
{
    /**
     * @param  list<array{requisito: Requisito, cubierto: bool, via: 'cv'|'anotada'|null, fragmento: string|null}>  $items
     * @param  bool  $conCv  si el candidato tiene un CV con texto que revisar
     */
    public function __construct(
        public array $items,
        public bool $conCv,
    ) {}

    public function total(): int
    {
        return count($this->items);
    }

    public function cubiertos(): int
    {
        return count(array_filter($this->items, fn (array $i) => $i['cubierto']));
    }

    /** @return list<string> nombres de los requisitos obligatorios que no se encontraron */
    public function obligatoriosFaltantes(): array
    {
        return array_values(array_map(
            fn (array $i) => (string) $i['requisito']->competencia?->nombre,
            array_filter($this->items, fn (array $i) => ! $i['cubierto'] && $i['requisito']->tipo === TipoRequisito::Obligatorio),
        ));
    }

    /** Porcentaje de requisitos cubiertos (entero), o null si la vacante no tiene requisitos. */
    public function porcentaje(): ?int
    {
        return $this->total() === 0 ? null : (int) round($this->cubiertos() * 100 / $this->total());
    }
}

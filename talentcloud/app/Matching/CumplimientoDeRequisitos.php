<?php

namespace App\Matching;

use App\Enums\NivelCoincidencia;
use App\Enums\TipoRequisito;
use App\Models\Requisito;
use Illuminate\Support\Collection;

/**
 * Cuántos de los requisitos *actuales* de una vacante cubre una postulación, según su
 * matching guardado. Se lee sobre los requisitos vigentes (no sobre lo calculado): si
 * se agregó uno después, figura «sin calcular» hasta el próximo cálculo.
 * Es una lectura de lo que ya explica el matching: no decide nada.
 */
final readonly class CumplimientoDeRequisitos
{
    /**
     * @param  list<array{id: int, competencia: string, obligatorio: bool, nivel: NivelCoincidencia|null}>  $requisitos
     */
    public function __construct(public array $requisitos) {}

    /**
     * @param  array<string, mixed>|null  $matchDetalle  postulaciones.match_detalle
     * @param  Collection<int, Requisito>  $requisitos  Los de la vacante, con su competencia cargada.
     */
    public static function de(?array $matchDetalle, Collection $requisitos): self
    {
        $niveles = [];

        if ($matchDetalle !== null && $matchDetalle !== []) {
            foreach (MatchResult::desdeArreglo($matchDetalle)->requisitos as $resultado) {
                $niveles[$resultado->requisitoId] = $resultado->nivel;
            }
        }

        return new self($requisitos->map(fn (Requisito $r) => [
            'id' => $r->id,
            'competencia' => (string) $r->competencia?->nombre,
            'obligatorio' => $r->tipo === TipoRequisito::Obligatorio,
            'nivel' => $niveles[$r->id] ?? null,
        ])->values()->all());
    }

    /**
     * El resumen que se guarda en `postulaciones` para ordenar y filtrar en SQL sin leer el JSON.
     *
     * @return array{requisitos_total: int, requisitos_cubiertos: int, obligatorios_faltantes: int}
     */
    public function columnas(): array
    {
        return [
            'requisitos_total' => $this->total(),
            'requisitos_cubiertos' => $this->cumplidos(),
            'obligatorios_faltantes' => $this->obligatoriosFaltantes(),
        ];
    }

    public function total(): int
    {
        return count($this->requisitos);
    }

    public function conNivel(NivelCoincidencia $nivel): int
    {
        return count(array_filter($this->requisitos, fn (array $r) => $r['nivel'] === $nivel));
    }

    public function cumplidos(): int
    {
        return $this->conNivel(NivelCoincidencia::Coincide);
    }

    public function porRevisar(): int
    {
        return $this->conNivel(NivelCoincidencia::Revisar);
    }

    public function faltantes(): int
    {
        return $this->conNivel(NivelCoincidencia::NoEncontrado);
    }

    /** ¿Hay un matching calculado sobre alguno de los requisitos? */
    public function hayCalculo(): bool
    {
        return count(array_filter($this->requisitos, fn (array $r) => $r['nivel'] !== null)) > 0;
    }

    /** Requisitos obligatorios que el CV no menciona: lo que más pesa al decidir a quién entrevistar. */
    public function obligatoriosFaltantes(): int
    {
        return count(array_filter($this->requisitos, fn (array $r) => $r['obligatorio'] && $r['nivel'] === NivelCoincidencia::NoEncontrado));
    }

    public function cubreLosObligatorios(): bool
    {
        return $this->hayCalculo() && $this->obligatoriosFaltantes() === 0;
    }

    /** «6 de 8 requisitos»: lo cubierto por el CV sobre el total pedido. */
    public function resumen(): string
    {
        return $this->cumplidos().' de '.$this->total().' '.($this->total() === 1 ? 'requisito' : 'requisitos');
    }
}

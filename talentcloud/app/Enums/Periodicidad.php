<?php

namespace App\Enums;

/** Cada cuánto se cobra un plan pagado en línea. */
enum Periodicidad: string
{
    use EsEnumDeEstado;

    case Mensual = 'mensual';
    case Anual = 'anual';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Mensual => 'Mensual',
            self::Anual => 'Anual',
        };
    }

    /** Lo que se cobra cada periodo, en soles sin IGV: "S/ 1 188 al año". */
    public function importe(PlanEmpresa $plan): string
    {
        $monto = match ($this) {
            self::Mensual => $plan->precioMensual(),
            self::Anual => (int) $plan->precioMensualAnual() * 12,
        };

        return 'S/ '.number_format($monto, 0, ',', ' ').($this === self::Mensual ? ' al mes' : ' al año');
    }
}

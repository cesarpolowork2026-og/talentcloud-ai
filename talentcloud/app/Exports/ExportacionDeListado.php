<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportación a Excel de un listado, con la misma consulta (filtros y orden)
 * que se ve en pantalla.
 *
 * Todo texto se escribe como texto literal: un valor como "=HYPERLINK(…)" en
 * el nombre de un candidato no se convierte en fórmula al abrir el archivo
 * (inyección de fórmulas).
 *
 * @template TModel of Model
 *
 * @implements WithMapping<TModel>
 */
abstract class ExportacionDeListado extends DefaultValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  Builder<TModel>  $consulta
     */
    public function __construct(private readonly Builder $consulta) {}

    /** @return list<string> */
    abstract public function headings(): array;

    /**
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return $this->consulta;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    protected static function fecha(?\DateTimeInterface $fecha): ?string
    {
        return $fecha?->format('Y-m-d');
    }
}

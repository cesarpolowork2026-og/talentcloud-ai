<?php

namespace App\Exports;

use App\Support\Formato;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exportación a Excel de un listado, con la misma consulta (filtros y orden)
 * que se ve en pantalla.
 *
 * Todo texto se escribe como literal para que un valor como "=HYPERLINK(...)"
 * no se convierta en fórmula al abrir el archivo. Cada exportación solo define
 * su nombre, las columnas de texto largo y, si quiere, `alTerminar`.
 *
 * @template TModel of Model
 *
 * @implements WithMapping<TModel>
 */
abstract class ExportacionDeListado extends DefaultValueBinder implements FromQuery, ShouldAutoSize, WithCustomStartCell, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping
{
    /** Fila del encabezado de la tabla: arriba van el título y la fecha. */
    protected const FILA_ENCABEZADO = 5;

    /** Azul petróleo de la marca (el de la barra superior de la aplicación). */
    protected const COLOR_MARCA = 'FF0B4A63';

    private const ANCHO_MAXIMO = 60;

    /**
     * @param  Builder<TModel>  $consulta
     */
    public function __construct(private readonly Builder $consulta) {}

    /** @return list<string> */
    abstract public function headings(): array;

    /** Título del archivo, en la primera fila ("Evaluaciones"). */
    abstract protected function titulo(): string;

    /**
     * Columnas (1 = A) con texto largo: se ensanchan hasta un tope y el texto baja de línea.
     *
     * @return list<int>
     */
    protected function columnasDeTexto(): array
    {
        return [];
    }

    /**
     * Ajustes propios de cada exportación sobre la hoja ya escrita.
     *
     * @param  int  $primera  Primera fila de datos.
     * @param  int  $ultima  Última fila de datos.
     */
    protected function alTerminar(Worksheet $hoja, int $primera, int $ultima): void {}

    /**
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return $this->consulta;
    }

    public function startCell(): string
    {
        return 'A'.self::FILA_ENCABEZADO;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $evento) => $this->darFormato($evento->sheet->getDelegate()),
        ];
    }

    private function darFormato(Worksheet $hoja): void
    {
        $columnas = count($this->headings());
        $ultimaColumna = Coordinate::stringFromColumnIndex($columnas);
        $encabezado = self::FILA_ENCABEZADO;
        $primera = $encabezado + 1;
        $ultima = max($primera, $hoja->getHighestRow());
        $hayDatos = $hoja->getHighestRow() >= $primera;
        $tabla = "A{$encabezado}:{$ultimaColumna}{$ultima}";

        // Cabecera del archivo: qué es, cuándo se generó y cuántas filas tiene.
        $filas = $hayDatos ? $ultima - $encabezado : 0;
        $hoja->setCellValueExplicit('A1', 'TalentCloud AI · '.$this->titulo(), DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('A2', 'Generado el '.Formato::fechaLarga(now()).' · '.$filas.' '.($filas === 1 ? 'registro' : 'registros'), DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('A3', 'Inteligencia que conecta talento. La decisión es siempre de la persona.', DataType::TYPE_STRING);
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB(self::COLOR_MARCA);
        $hoja->getStyle('A2')->getFont()->setSize(11)->getColor()->setARGB('FF3B4048');
        $hoja->getStyle('A3')->getFont()->setItalic(true)->setSize(10)->getColor()->setARGB('FF5B616B');
        $hoja->getRowDimension(1)->setRowHeight(26);

        // Encabezado de la tabla.
        $hoja->getStyle("A{$encabezado}:{$ultimaColumna}{$encabezado}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_MARCA]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $hoja->getRowDimension($encabezado)->setRowHeight(24);

        if (! $hayDatos) {
            return;
        }

        // Cuerpo: bordes finos, alineado arriba y filas alternadas (con una regla, no celda por celda).
        $hoja->getStyle($tabla)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD9DCDF']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
        ]);

        $alterna = new Conditional;
        $alterna->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition('MOD(ROW(),2)=1');
        $alterna->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setARGB('FFF3F6F8');
        $hoja->getStyle("A{$primera}:{$ultimaColumna}{$ultima}")->setConditionalStyles([$alterna]);

        foreach ($this->columnasDeTexto() as $indice) {
            $letra = Coordinate::stringFromColumnIndex($indice);
            $hoja->getColumnDimension($letra)->setAutoSize(false)->setWidth(self::ANCHO_MAXIMO);
            $hoja->getStyle("{$letra}{$primera}:{$letra}{$ultima}")->getAlignment()->setWrapText(true);
        }

        $hoja->freezePane('A'.$primera);
        $hoja->setAutoFilter($tabla);

        $this->alTerminar($hoja, $primera, $ultima);
    }

    /**
     * Semáforo para puntajes de 0 a 10 (verde desde 8, ámbar desde 6, rojo por debajo):
     * el color acompaña al número, que sigue siendo lo que se lee.
     *
     * @param  string  $rango  Por ejemplo "D6:H40".
     */
    protected function semaforoDePuntajes(Worksheet $hoja, string $rango): void
    {
        $reglas = [];

        foreach ([
            [Conditional::OPERATOR_GREATERTHANOREQUAL, '8', 'FFDCF2E3', 'FF166534'],
            [Conditional::OPERATOR_GREATERTHANOREQUAL, '6', 'FFFCEFD0', 'FF7A4B00'],
            [Conditional::OPERATOR_LESSTHAN, '6', 'FFF8E0E8', 'FF9B1D4F'],
        ] as [$operador, $valor, $fondo, $texto]) {
            $regla = new Conditional;
            $regla->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType($operador)->addCondition($valor);
            $regla->setStopIfTrue(true);
            $regla->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setARGB($fondo);
            $regla->getStyle()->getFont()->getColor()->setARGB($texto);
            $reglas[] = $regla;
        }

        // Las celdas vacías no se pintan (en blanco valdrían 0).
        $vacia = new Conditional;
        $vacia->setConditionType(Conditional::CONDITION_EXPRESSION)->addCondition('ISBLANK('.strtok($rango, ':').')');
        $vacia->setStopIfTrue(true);

        $hoja->getStyle($rango)->setConditionalStyles([$vacia, ...$reglas]);
    }

    protected static function fecha(?\DateTimeInterface $fecha): ?string
    {
        return $fecha?->format('Y-m-d');
    }
}

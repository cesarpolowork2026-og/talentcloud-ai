<?php

namespace App\Services\Cvs\Extraccion;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use Throwable;

class ExtractorDocx implements ExtractorDeTexto
{
    public function soporta(string $mime): bool
    {
        return $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    public function extraer(string $contenido): string
    {
        // PhpWord lee desde un archivo: se usa uno temporal que se borra al terminar.
        $temporal = tempnam(sys_get_temp_dir(), 'cv');

        if ($temporal === false) {
            throw new ArchivoIlegible('No se pudo preparar el archivo para leerlo.');
        }

        try {
            file_put_contents($temporal, $contenido);
            $documento = IOFactory::load($temporal, 'Word2007');

            $lineas = [];
            foreach ($documento->getSections() as $seccion) {
                foreach ($seccion->getElements() as $elemento) {
                    $lineas[] = $this->texto($elemento);
                }
            }

            return implode("\n", $lineas);
        } catch (Throwable $e) {
            throw new ArchivoIlegible('No se pudo leer el documento de Word. Verifica que sea un .docx válido.', previous: $e);
        } finally {
            @unlink($temporal);
        }
    }

    private function texto(AbstractElement $elemento): string
    {
        return match (true) {
            $elemento instanceof Text => $elemento->getText(),
            $elemento instanceof TextBreak => "\n",
            $elemento instanceof TextRun => implode('', array_map(fn ($e) => $this->texto($e), $elemento->getElements())),
            $elemento instanceof Table => $this->tabla($elemento),
            $elemento instanceof AbstractContainer => implode("\n", array_map(fn ($e) => $this->texto($e), $elemento->getElements())),
            method_exists($elemento, 'getText') && is_string($elemento->getText()) => $elemento->getText(),
            default => '',
        };
    }

    private function tabla(Table $tabla): string
    {
        $filas = [];
        foreach ($tabla->getRows() as $fila) {
            $celdas = [];
            foreach ($fila->getCells() as $celda) {
                $celdas[] = implode(' ', array_map(fn ($e) => $this->texto($e), $celda->getElements()));
            }
            $filas[] = implode(' | ', $celdas);
        }

        return implode("\n", $filas);
    }
}

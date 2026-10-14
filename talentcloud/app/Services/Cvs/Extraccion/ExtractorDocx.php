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
use ZipArchive;

class ExtractorDocx implements ExtractorDeTexto
{
    /**
     * Un .docx es un ZIP: uno de 5 MB puede descomprimirse a gigabytes («bomba ZIP») y agotar la memoria del
     * worker con un error fatal que ninguna excepción captura. Un CV real pesa muy por debajo de estos topes.
     */
    public const MAX_BYTES_DESCOMPRIMIDOS = 50 * 1024 * 1024;

    public const MAX_ENTRADAS = 1000;

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
            $this->verificarTamanoDescomprimido($temporal);
            $documento = IOFactory::load($temporal, 'Word2007');

            $lineas = [];
            foreach ($documento->getSections() as $seccion) {
                foreach ($seccion->getElements() as $elemento) {
                    $lineas[] = $this->texto($elemento);
                }
            }

            return implode("\n", $lineas);
        } catch (ArchivoIlegible $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ArchivoIlegible('No se pudo leer el documento de Word. Verifica que sea un .docx válido.', previous: $e);
        } finally {
            @unlink($temporal);
        }
    }

    /** Lee solo el índice del ZIP (tamaños declarados): no descomprime nada. */
    private function verificarTamanoDescomprimido(string $ruta): void
    {
        $zip = new ZipArchive;

        if ($zip->open($ruta, ZipArchive::RDONLY) !== true) {
            throw new ArchivoIlegible('No se pudo leer el documento de Word. Verifica que sea un .docx válido.');
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRADAS) {
                throw new ArchivoIlegible('El documento de Word es demasiado complejo para leerlo. Guárdalo de nuevo como PDF o .docx simple.');
            }

            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $total += (int) ($zip->statIndex($i)['size'] ?? 0);

                if ($total > self::MAX_BYTES_DESCOMPRIMIDOS) {
                    throw new ArchivoIlegible('El documento de Word es demasiado grande para leerlo. Guárdalo de nuevo como PDF o .docx simple.');
                }
            }
        } finally {
            $zip->close();
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

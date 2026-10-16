<?php

namespace App\Console\Commands;

use App\Models\Cv;
use Database\Seeders\Demo\DocumentosDePrueba;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Un volcado de la base lleva las filas de los CV pero no sus archivos. Este comando crea los que faltan en el disco
 * a partir del texto guardado, con el mismo generador que usa la siembra de demostración.
 *
 * Solo toca candidatos de demostración (por defecto, correos @correo.com) y nunca pisa un archivo que ya existe:
 * así no puede reemplazar el CV de una persona real por un documento generado.
 */
class RegenerarCvsDeDemostracion extends Command
{
    protected $signature = 'talentcloud:regenerar-cvs-demo {--dominio=correo.com : Solo candidatos con correo de este dominio}';

    protected $description = 'Crea los archivos que faltan de los CV de demostración, a partir del texto guardado';

    public function handle(): int
    {
        $dominio = ltrim((string) $this->option('dominio'), '@');
        $patron = '%@'.addcslashes($dominio, '%_\\');

        $creados = 0;
        $yaExistian = 0;

        // Los CV y los candidatos son globales (sin empresa): no hace falta contexto de tenant.
        Cv::query()
            ->whereHas('candidato', fn ($q) => $q->where('email', 'ilike', $patron))
            ->chunkById(100, function ($cvs) use (&$creados, &$yaExistian): void {
                foreach ($cvs as $cv) {
                    $disco = Storage::disk($cv->disco);

                    if ($disco->exists($cv->ruta)) {
                        $yaExistian++;

                        continue;
                    }

                    $esPdf = strtolower(pathinfo($cv->ruta, PATHINFO_EXTENSION)) === 'pdf';

                    // Sin texto, el PDF es una página sin texto seleccionable (como un escaneado).
                    $disco->put($cv->ruta, $esPdf
                        ? DocumentosDePrueba::pdf($cv->texto_extraido)
                        : DocumentosDePrueba::docx((string) $cv->texto_extraido));
                    $creados++;
                }
            });

        $this->info("Archivos creados: {$creados}. Ya existían: {$yaExistian}.");

        return self::SUCCESS;
    }
}

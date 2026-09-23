<?php

namespace App\Services\Vacantes;

use App\Ia\RespuestaIaNoValida;
use App\Ia\ServicioIa;
use App\Models\Requisito;
use App\Models\Vacante;
use Illuminate\Support\Facades\DB;
use Pgvector\Laravel\Vector;

/**
 * Genera el embedding de cada requisito de una vacante. Se recalculan todos
 * (son pocos): así un requisito editado nunca conserva un vector antiguo.
 */
class IndexarRequisitos
{
    public function __construct(private readonly ServicioIa $ia) {}

    /** Cantidad de requisitos indexados. */
    public function ejecutar(Vacante $vacante): int
    {
        $requisitos = $vacante->requisitos()->with('competencia')->get();

        if ($requisitos->isEmpty()) {
            return 0;
        }

        $generados = $this->ia->generarEmbeddings(
            $requisitos->mapWithKeys(fn (Requisito $r) => [$r->id => self::textoParaEmbedding($r)])->all(),
        );

        DB::transaction(function () use ($requisitos, $generados) {
            foreach ($requisitos as $requisito) {
                $vector = $generados->vectorDe($requisito->id) ?? throw RespuestaIaNoValida::campo("embeddings[id={$requisito->id}]");

                $requisito->forceFill(['embedding' => new Vector($vector), 'embedding_modelo' => $generados->modelo])->save();
            }
        });

        return $requisitos->count();
    }

    /** "Kubernetes (k8s): al menos 2 años de experiencia. Operación de clústeres en producción." */
    public static function textoParaEmbedding(Requisito $requisito): string
    {
        $competencia = $requisito->competencia;
        $sinonimos = $competencia->sinonimos ?? [];

        $texto = $competencia->nombre.($sinonimos !== [] ? ' ('.implode(', ', $sinonimos).')' : '');

        if ($requisito->anios_minimos) {
            $texto .= ': al menos '.$requisito->anios_minimos.' '.($requisito->anios_minimos === 1 ? 'año' : 'años').' de experiencia';
        }

        if (filled($requisito->descripcion)) {
            $texto .= '. '.rtrim((string) $requisito->descripcion, '.');
        }

        return $texto.'.';
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\TipoRequisito;
use App\Matching\FakeMatchingEngine;
use App\Matching\MatchInput;
use App\Matching\RequisitoInput;
use App\Matching\RequisitoMatch;
use Illuminate\Console\Command;

/**
 * Pasa el análisis básico (FakeMatchingEngine) por el conjunto de evaluación del
 * servicio de IA y guarda sus predicciones, para compararlo con el servicio en
 * talentcloud-ai/evaluacion/evaluar.py. Herramienta de medición, sin efectos en la base de datos.
 */
class EvaluarMotorBasico extends Command
{
    protected $signature = 'talentcloud:evaluar-motor-basico
        {--salida= : Archivo de salida (por defecto, talentcloud-ai/evaluacion/predicciones_motor_basico.json)}';

    protected $description = 'Predicciones del análisis básico sobre el conjunto de evaluación del matching';

    public function handle(FakeMatchingEngine $motor): int
    {
        $directorio = dirname(base_path()).'/talentcloud-ai';
        $conjunto = json_decode((string) file_get_contents($directorio.'/evaluacion/conjunto.json'), true, flags: JSON_THROW_ON_ERROR);

        $requisitos = array_map(fn (array $r) => new RequisitoInput(
            id: $r['id'],
            competencia: $r['competencia'],
            sinonimos: $r['sinonimos'],
            tipo: TipoRequisito::Obligatorio,
            peso: 3,
            aniosMinimos: $r['anios_minimos'],
            descripcion: $r['descripcion'],
        ), $conjunto['requisitos']);

        $casos = [];
        foreach ($conjunto['cvs'] as $cv) {
            $texto = (string) file_get_contents("{$directorio}/tests/datos/cvs/{$cv}.txt");
            $resultado = $motor->evaluar(new MatchInput(1, 'Conjunto de evaluación', null, $requisitos, 1, $texto));

            foreach ($resultado->requisitos as $requisito) {
                $casos[] = $this->caso($cv, $requisito);
            }
        }

        $salida = $this->option('salida') ?: $directorio.'/evaluacion/predicciones_motor_basico.json';
        file_put_contents($salida, json_encode(['motor' => 'fake', 'casos' => $casos], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");

        $this->info(count($casos)." predicciones en {$salida}");

        return self::SUCCESS;
    }

    /**
     * @return array{cv: string, requisito_id: int, nivel: string, porcentaje: int}
     */
    private function caso(string $cv, RequisitoMatch $requisito): array
    {
        return [
            'cv' => $cv,
            'requisito_id' => $requisito->requisitoId,
            'nivel' => $requisito->nivel->value,
            'porcentaje' => $requisito->porcentaje,
        ];
    }
}

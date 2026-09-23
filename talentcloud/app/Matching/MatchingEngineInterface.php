<?php

namespace App\Matching;

/**
 * Adapter del motor de matching (patrón del stack). Implementaciones:
 * - FakeMatchingEngine: reglas ponderadas y palabras clave (tests y respaldo);
 * - HttpMatchingEngine: servicio de IA en FastAPI (día 15).
 * Se elige con MATCHING_ENGINE=fake|http (config/talentcloud.php).
 */
interface MatchingEngineInterface
{
    public function evaluar(MatchInput $entrada): MatchResult;
}

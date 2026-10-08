<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * PostgreSQL no crea índices para las claves foráneas. Estas columnas se
 * consultan a menudo: el filtro por empresa de cada consulta (empresa_id),
 * la visibilidad de candidatos, "Mis postulaciones", las entrevistas de una
 * postulación y la búsqueda de candidatos por competencia.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const INDICES = [
        'users' => ['empresa_id'],
        'candidatos' => ['empresa_origen_id'],
        'postulaciones' => ['candidato_id'],
        'entrevistas' => ['postulacion_id'],
        'requisitos' => ['empresa_id', 'competencia_id'],
        'candidato_competencia' => ['competencia_id', 'empresa_id'],
        'match_feedback' => ['empresa_id'],
        'notas' => ['empresa_id'],
    ];

    public function up(): void
    {
        foreach (self::INDICES as $tabla => $columnas) {
            Schema::table($tabla, function (Blueprint $table) use ($columnas) {
                foreach ($columnas as $columna) {
                    $table->index($columna);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDICES as $tabla => $columnas) {
            Schema::table($tabla, function (Blueprint $table) use ($columnas) {
                foreach ($columnas as $columna) {
                    $table->dropIndex([$columna]);
                }
            });
        }
    }
};

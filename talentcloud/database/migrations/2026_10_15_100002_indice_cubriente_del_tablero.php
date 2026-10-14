<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * El índice del tablero (empresa, vacante, etapa, orden) pasa a «cubrir» las columnas que leen las cifras por
 * vacante del Dashboard (por revisar, esperando, estancadas, mejor match). Con ellas dentro del índice, PostgreSQL
 * resuelve esas agregaciones sin leer la tabla, que es ancha por el JSON del matching: con 120 000 postulaciones,
 * de 180 ms y 60 000 páginas leídas a 80 ms y unas 1 900.
 *
 * No suma un índice a una tabla que recibe muchas escrituras: sustituye al anterior, que tenía las mismas
 * columnas clave. Las columnas añadidas ya cambiaban junto con la etapa o el matching, que están indexados.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE INDEX postulaciones_tablero_index ON postulaciones (empresa_id, vacante_id, etapa, orden) '
            .'INCLUDE (retirada_at, etapa_cambiada_at, created_at, match_score)'
        );
        DB::statement('DROP INDEX postulaciones_empresa_id_vacante_id_etapa_orden_index');
    }

    public function down(): void
    {
        DB::statement('CREATE INDEX postulaciones_empresa_id_vacante_id_etapa_orden_index ON postulaciones (empresa_id, vacante_id, etapa, orden)');
        DB::statement('DROP INDEX postulaciones_tablero_index');
    }
};

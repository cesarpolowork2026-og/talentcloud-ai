<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El ranking y los filtros de «Selección» no pueden leer el JSON del matching de cada postulación
 * (con miles por vacante agotan la memoria): el resumen de cumplimiento vive en columnas con índice,
 * que mantiene CalcularMatch cada vez que calcula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulaciones', function (Blueprint $table): void {
            $table->unsignedSmallInteger('requisitos_total')->nullable()->after('match_calculado_at');
            $table->unsignedSmallInteger('requisitos_cubiertos')->nullable()->after('requisitos_total');
            $table->unsignedSmallInteger('obligatorios_faltantes')->nullable()->after('requisitos_cubiertos');
        });

        // Lo ya calculado: sobre los requisitos vigentes de cada vacante, como CumplimientoDeRequisitos::de().
        DB::statement(<<<'SQL'
            UPDATE postulaciones p SET
                requisitos_total = (SELECT count(*) FROM requisitos r WHERE r.vacante_id = p.vacante_id),
                requisitos_cubiertos = (
                    SELECT count(*) FROM requisitos r
                    JOIN jsonb_array_elements(p.match_detalle -> 'requisitos') d ON (d ->> 'requisito_id')::bigint = r.id
                    WHERE r.vacante_id = p.vacante_id AND d ->> 'nivel' = 'coincide'
                ),
                obligatorios_faltantes = (
                    SELECT count(*) FROM requisitos r
                    JOIN jsonb_array_elements(p.match_detalle -> 'requisitos') d ON (d ->> 'requisito_id')::bigint = r.id
                    WHERE r.vacante_id = p.vacante_id AND r.tipo = 'obligatorio' AND d ->> 'nivel' = 'no_encontrado'
                )
            WHERE p.match_detalle IS NOT NULL
        SQL);

        // El ranking de una vacante: de mayor a menor coincidencia.
        DB::statement('CREATE INDEX postulaciones_vacante_match_index ON postulaciones (empresa_id, vacante_id, match_score DESC NULLS LAST, id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS postulaciones_vacante_match_index');

        Schema::table('postulaciones', function (Blueprint $table): void {
            $table->dropColumn(['requisitos_total', 'requisitos_cubiertos', 'obligatorios_faltantes']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Las competencias manuales son anotaciones de una empresa sobre un candidato
 * (que es global): cada empresa ve y edita solo las suyas. Las extraídas del
 * CV por la IA no tienen empresa (empresa_id nulo) y las ven todas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidato_competencia', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->after('competencia_id')->constrained('empresas')->cascadeOnDelete();
            $table->dropUnique(['candidato_id', 'competencia_id', 'fuente']);
        });

        // Las manuales existentes pasan a la empresa que registró al candidato o,
        // si llegó por su cuenta, a la de su primera postulación.
        DB::statement("
            update candidato_competencia cc
            set empresa_id = coalesce(c.empresa_origen_id, (
                select p.empresa_id from postulaciones p
                where p.candidato_id = c.id
                order by p.created_at, p.id
                limit 1
            ))
            from candidatos c
            where c.id = cc.candidato_id and cc.fuente = 'manual'
        ");

        // NULLS NOT DISTINCT (PostgreSQL 15+): una sola fila extraída por competencia.
        DB::statement('
            create unique index candidato_competencia_unica
            on candidato_competencia (candidato_id, competencia_id, fuente, empresa_id) nulls not distinct
        ');
    }

    public function down(): void
    {
        DB::statement('drop index candidato_competencia_unica');
        DB::statement('delete from candidato_competencia a using candidato_competencia b
            where a.id > b.id and a.candidato_id = b.candidato_id and a.competencia_id = b.competencia_id and a.fuente = b.fuente');

        Schema::table('candidato_competencia', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_id');
            $table->unique(['candidato_id', 'competencia_id', 'fuente']);
        });
    }
};

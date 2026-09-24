<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una evaluación por evaluador y postulación (la regla la aplica también el
 * servicio, con un mensaje claro), e índice para buscar solapamientos en la
 * agenda de cada entrevistador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->unique(['postulacion_id', 'evaluador_id']);
        });

        Schema::table('entrevistas', function (Blueprint $table) {
            $table->index(['entrevistador_id', 'inicia_at']);
        });
    }

    public function down(): void
    {
        Schema::table('entrevistas', function (Blueprint $table) {
            $table->dropIndex(['entrevistador_id', 'inicia_at']);
        });

        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->dropUnique(['postulacion_id', 'evaluador_id']);
        });
    }
};

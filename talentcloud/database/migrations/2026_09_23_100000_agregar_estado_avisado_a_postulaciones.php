<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Último estado de la postulación que se comunicó al postulante (valor de
 * EstadoDeMiPostulacion). Evita avisos repetidos cuando el reclutador mueve la
 * tarjeta varias veces. Nulo en las postulaciones existentes: no se les avisa
 * de nada hasta su próximo cambio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->string('estado_avisado', 20)->nullable()->after('etapa_cambiada_at');
        });
    }

    public function down(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->dropColumn('estado_avisado');
        });
    }
};

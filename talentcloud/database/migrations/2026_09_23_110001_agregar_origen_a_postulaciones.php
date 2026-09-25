<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Origen de la postulación (valor de OrigenPostulacion): "equipo" si la creó la
 * empresa, "postulante" si el candidato se postuló por su cuenta. Las
 * existentes quedan como "equipo", que es el comportamiento anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->string('origen', 20)->default('equipo')->after('etapa_cambiada_at');
        });

        // Las propias anteriores a esta columna se reconocen por su actividad "postulacion.propia".
        DB::table('postulaciones')
            ->whereIn('id', DB::table('actividades')
                ->select('sujeto_id')
                ->where('tipo', 'postulacion.propia')
                ->where('sujeto_type', 'App\Models\Postulacion'))
            ->update(['origen' => 'postulante']);
    }

    public function down(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->dropColumn('origen');
        });
    }
};

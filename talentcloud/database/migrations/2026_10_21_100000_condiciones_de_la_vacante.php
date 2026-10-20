<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Condiciones opcionales de una vacante, para que quien postula las vea antes de crear su cuenta: la jornada y el rango
 * de sueldo mensual en soles. Todo es opcional; ninguna vacante existente cambia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacantes', function (Blueprint $table) {
            $table->string('jornada', 20)->nullable();
            $table->unsignedInteger('sueldo_minimo')->nullable();
            $table->unsignedInteger('sueldo_maximo')->nullable();
        });

        DB::statement("ALTER TABLE vacantes ADD CONSTRAINT vacantes_jornada_check CHECK (jornada IS NULL OR jornada IN ('completa', 'parcial'))");
        DB::statement('ALTER TABLE vacantes ADD CONSTRAINT vacantes_sueldo_check CHECK ('
            .'(sueldo_minimo IS NULL OR sueldo_minimo BETWEEN 1 AND 1000000) '
            .'AND (sueldo_maximo IS NULL OR sueldo_maximo BETWEEN 1 AND 1000000) '
            .'AND (sueldo_minimo IS NULL OR sueldo_maximo IS NULL OR sueldo_maximo >= sueldo_minimo))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vacantes DROP CONSTRAINT IF EXISTS vacantes_sueldo_check');
        DB::statement('ALTER TABLE vacantes DROP CONSTRAINT IF EXISTS vacantes_jornada_check');

        Schema::table('vacantes', function (Blueprint $table) {
            $table->dropColumn(['jornada', 'sueldo_minimo', 'sueldo_maximo']);
        });
    }
};

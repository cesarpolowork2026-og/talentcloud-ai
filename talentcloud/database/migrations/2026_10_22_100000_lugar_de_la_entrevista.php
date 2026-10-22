<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Dónde se hace una entrevista que no es una videollamada: la dirección de una presencial o el teléfono (o la indicación)
 * de una telefónica. Hasta ahora una entrevista solo guardaba un enlace, y solo para videollamada, así que quien iba a una
 * entrevista presencial no sabía adónde ir. Es opcional en la base: las entrevistas anteriores no cambian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entrevistas', function (Blueprint $table) {
            $table->string('lugar')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('entrevistas', function (Blueprint $table) {
            $table->dropColumn('lugar');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Fecha en que el propio postulante retiró su postulación. La postulación pasa
 * a la columna Descartado del Kanban, pero se distingue de un descarte de la
 * empresa: el postulante la ve "Retirada" y la actividad lo dice así.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->timestamp('retirada_at')->nullable()->after('origen');
        });
    }

    public function down(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->dropColumn('retirada_at');
        });
    }
};

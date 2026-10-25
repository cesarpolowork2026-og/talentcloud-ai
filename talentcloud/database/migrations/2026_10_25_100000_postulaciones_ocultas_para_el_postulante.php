<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * «Quitar de mi lista» del postulante: la postulación deja de verse en «Mis postulaciones», pero no se borra (la empresa
 * conserva su historial, su tablero y su embudo). Es opcional en la base: las postulaciones existentes siguen a la vista.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->timestamp('oculta_para_postulante_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->dropColumn('oculta_para_postulante_at');
        });
    }
};

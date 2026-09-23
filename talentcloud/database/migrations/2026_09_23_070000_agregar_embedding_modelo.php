<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo que generó cada embedding: vectores de modelos distintos no son
 * comparables, así que al cambiar de modelo hay que reindexar.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['requisitos', 'cv_fragmentos'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('embedding_modelo', 120)->nullable()->after('embedding');
            });
        }
    }

    public function down(): void
    {
        foreach (['requisitos', 'cv_fragmentos'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('embedding_modelo');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * "Marcar como incorrecto" de la pantalla de Matching: el reclutador señala
 * un resultado de la IA que no comparte. Se usa para recalibrar umbrales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('postulacion_id')->constrained('postulaciones')->cascadeOnDelete();
            $table->foreignId('requisito_id')->constrained('requisitos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comentario')->nullable();
            $table->timestamps();

            $table->unique(['postulacion_id', 'requisito_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_feedback');
    }
};

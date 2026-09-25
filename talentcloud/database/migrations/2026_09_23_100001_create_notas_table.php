<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Notas internas del equipo sobre una postulación ("Buena comunicación, pedir
 * referencias"). Son de la empresa: nunca las ve el postulante ni otra empresa.
 * Se borran con la postulación (y, por tanto, al eliminar la cuenta del postulante).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('postulacion_id')->constrained('postulaciones')->cascadeOnDelete();
            $table->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('texto');
            $table->timestamps();

            $table->index(['postulacion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas');
    }
};

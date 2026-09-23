<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidato_competencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidato_id')->constrained('candidatos')->cascadeOnDelete();
            $table->foreignId('competencia_id')->constrained('competencias')->restrictOnDelete();
            $table->string('fuente', 20)->default('manual');
            $table->decimal('anios', 4, 1)->nullable();
            // Fragmento del CV que la respalda (solo para fuente = extraccion).
            $table->text('evidencia')->nullable();
            $table->timestamps();

            $table->unique(['candidato_id', 'competencia_id', 'fuente']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidato_competencia');
    }
};

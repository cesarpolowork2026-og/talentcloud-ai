<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('postulacion_id')->constrained('postulaciones')->cascadeOnDelete();
            $table->foreignId('evaluador_id')->constrained('users')->restrictOnDelete();
            // [{"nombre": "Conocimiento técnico", "puntaje": 9}, …]
            $table->jsonb('criterios')->default('[]');
            // Promedio de los criterios, de 0 a 10 (se muestra "8.6/10").
            $table->decimal('puntaje', 3, 1)->nullable();
            $table->text('comentario')->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->timestamps();

            $table->index(['empresa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones');
    }
};

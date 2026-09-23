<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Feed "Actividad reciente" del dashboard: "Sofía Nieto avanzó a la etapa de
 * entrevista.", "La IA analizó 6 CVs nuevos…".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 60);
            $table->string('descripcion');
            $table->nullableMorphs('sujeto');
            $table->timestamps();

            $table->index(['empresa_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrevistas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('postulacion_id')->constrained('postulaciones')->cascadeOnDelete();
            $table->foreignId('entrevistador_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('inicia_at');
            $table->unsignedSmallInteger('duracion_min')->default(45);
            $table->string('modalidad', 20)->default('videollamada');
            $table->string('enlace')->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'inicia_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrevistas');
    }
};

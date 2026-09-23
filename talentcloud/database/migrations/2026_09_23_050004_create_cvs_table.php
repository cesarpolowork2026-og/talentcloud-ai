<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Archivos de CV. Global como el candidato: la visibilidad por empresa se
 * aplica a través del candidato. El archivo vive en un disco privado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cvs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidato_id')->constrained('candidatos')->cascadeOnDelete();
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disco', 40);
            $table->string('ruta');
            $table->string('nombre_original');
            $table->string('mime', 120);
            $table->unsignedInteger('tamano');
            $table->longText('texto_extraido')->nullable();
            // Resultado de la extracción con IA: secciones, competencias, años, cargos…
            $table->jsonb('datos_estructurados')->nullable();
            $table->string('estado_procesamiento', 20)->default('pendiente');
            $table->text('error')->nullable();
            $table->timestamp('procesado_at')->nullable();
            $table->timestamps();

            $table->index(['candidato_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cvs');
    }
};

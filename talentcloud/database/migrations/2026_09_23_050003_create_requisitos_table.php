<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Requisitos de una vacante. Lleva empresa_id (además de vacante_id) para
 * que el filtro por empresa también lo proteja cuando se consulta directo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('vacante_id')->constrained('vacantes')->cascadeOnDelete();
            $table->foreignId('competencia_id')->constrained('competencias')->restrictOnDelete();
            // Detalle opcional: "APIs REST en producción", "certificación vigente"…
            $table->string('descripcion')->nullable();
            $table->string('tipo', 20)->default('obligatorio');
            // Peso relativo en el score global (1 a 5).
            $table->unsignedTinyInteger('peso')->default(3);
            $table->unsignedTinyInteger('anios_minimos')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            // Embedding del requisito (paraphrase-multilingual-MiniLM-L12-v2).
            $table->vector('embedding', 384)->nullable();
            $table->timestamps();

            $table->unique(['vacante_id', 'competencia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitos');
    }
};

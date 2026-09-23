<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postulaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('vacante_id')->constrained('vacantes')->cascadeOnDelete();
            $table->foreignId('candidato_id')->constrained('candidatos')->cascadeOnDelete();
            // CV con el que postuló (se conserva aunque suba uno nuevo).
            $table->foreignId('cv_id')->nullable()->constrained('cvs')->nullOnDelete();
            $table->string('etapa', 20)->default('postulado');
            // Posición dentro de la columna del Kanban.
            $table->unsignedInteger('orden')->default(0);
            $table->timestamp('etapa_cambiada_at')->nullable();
            // Resultado del matching (MatchResult serializado). La IA sugiere; no decide.
            $table->decimal('match_score', 5, 2)->nullable();
            $table->jsonb('match_detalle')->nullable();
            $table->string('match_motor', 10)->nullable();
            $table->timestamp('match_calculado_at')->nullable();
            $table->timestamps();

            $table->unique(['vacante_id', 'candidato_id']);
            $table->index(['empresa_id', 'vacante_id', 'etapa', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postulaciones');
    }
};

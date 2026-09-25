<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Historial de etapas de cada postulación (una fila por cada vez que entra en
 * una etapa). Lo escribe PostulacionObserver y alimenta las métricas del
 * embudo. Las postulaciones existentes se rellenan con su etapa actual: de
 * ellas solo se sabe dónde están, no por dónde pasaron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postulacion_etapas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('postulacion_id')->constrained('postulaciones')->cascadeOnDelete();
            $table->string('etapa', 20);
            $table->timestamp('desde');

            $table->index(['empresa_id', 'postulacion_id']);
            $table->index(['postulacion_id', 'etapa']);
        });

        DB::statement(<<<'SQL'
            INSERT INTO postulacion_etapas (empresa_id, postulacion_id, etapa, desde)
            SELECT empresa_id, id, etapa, COALESCE(etapa_cambiada_at, created_at, NOW())
            FROM postulaciones
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('postulacion_etapas');
    }
};

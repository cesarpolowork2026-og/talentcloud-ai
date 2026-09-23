<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->string('ubicacion')->nullable();
            $table->string('modalidad', 20)->default('remoto');
            $table->string('estado', 20)->default('borrador');
            $table->timestamp('publicada_at')->nullable();
            $table->timestamp('cerrada_at')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacantes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Catálogo compartido de competencias (Java, Spring Boot, Figma…). Es global:
 * normaliza lo que piden las vacantes y lo que se extrae de los CV.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            // Minúsculas y sin tildes: evita duplicados como "PostgreSQL" / "postgresql".
            $table->string('nombre_normalizado')->unique();
            $table->string('categoria')->nullable();
            // Variantes que la extracción reconoce: ["Postgres", "psql"].
            $table->jsonb('sinonimos')->default('[]');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competencias');
    }
};

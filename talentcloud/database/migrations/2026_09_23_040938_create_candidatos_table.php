<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Los candidatos son globales (no pertenecen a una empresa): un mismo
 * postulante puede aplicar a vacantes de varias empresas. Cada empresa solo
 * ve a los que postularon a sus vacantes o a los que ella registró
 * (empresa_origen_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidatos', function (Blueprint $table) {
            $table->id();
            // Cuenta del postulante; nula si el reclutador lo registró sin cuenta.
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('empresa_origen_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('nombre');
            $table->string('email')->nullable()->index();
            $table->string('telefono', 40)->nullable();
            // Titular profesional: "Desarrolladora Backend", "Analista de Datos"…
            $table->string('titular')->nullable();
            // Aceptación del tratamiento de datos personales (incluido el análisis del CV con IA).
            $table->timestamp('consentimiento_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidatos');
    }
};

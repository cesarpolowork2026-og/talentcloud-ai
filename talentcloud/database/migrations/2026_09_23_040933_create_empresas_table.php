<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('sector')->nullable();
            $table->string('sitio_web')->nullable();
            $table->text('descripcion')->nullable();
            // Informativo hasta integrar Stripe + Cashier.
            $table->string('plan', 20)->default('starter');
            $table->string('estado', 20)->default('activa')->index();
            // Pasos del checklist de bienvenida: {"perfil": bool, "vacante": bool, "invitar": bool}.
            $table->jsonb('onboarding')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};

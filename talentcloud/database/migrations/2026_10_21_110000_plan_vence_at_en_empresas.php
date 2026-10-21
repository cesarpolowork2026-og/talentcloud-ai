<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Vencimiento de un plan asignado a mano por el admin SaaS (transferencia, factura, contrato). Es opcional: sin fecha
 * el plan no vence (p. ej. un contrato Enterprise). Al pasar la fecha rige el plan Gratis sin tocar nada, igual que la
 * prueba. Un plan pagado con tarjeta no la usa: su fin de periodo es suscripcion_renueva_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->timestamp('plan_vence_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('plan_vence_at');
        });
    }
};

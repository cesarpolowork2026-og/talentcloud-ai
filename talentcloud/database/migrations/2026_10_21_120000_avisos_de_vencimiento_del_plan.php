<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Qué vencimiento (plan_vence_at) ya se avisó a la empresa, para no repetir el correo cada día: cada columna guarda la
 * fecha de vencimiento a la que se refería el aviso. Si el admin renueva con otra fecha, ya no coincide y vuelve a
 * avisarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->timestamp('plan_aviso_pronto_para')->nullable();
            $table->timestamp('plan_aviso_vencido_para')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['plan_aviso_pronto_para', 'plan_aviso_vencido_para']);
        });
    }
};

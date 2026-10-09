<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planes con capa gratuita: prueba del plan Pro al registrarse, solicitud de
 * cambio de plan (sin pasarela de pago todavía) y cupo mensual de matching con IA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('plan', 20)->default('gratis')->change();
            // Hasta cuándo dura la prueba del plan Pro (null = sin prueba).
            $table->timestamp('prueba_hasta')->nullable()->after('plan');
            // Plan que pidió la cuenta de empresa y aún no aplicó el admin SaaS.
            $table->string('plan_solicitado', 20)->nullable()->after('prueba_hasta');
            $table->timestamp('plan_solicitado_at')->nullable()->after('plan_solicitado');
        });

        Schema::table('postulaciones', function (Blueprint $table) {
            // Cuándo consumió la postulación su lugar del cupo de IA (una vez; recalcular no vuelve a consumir).
            $table->timestamp('ia_cupo_at')->nullable();
            // Se evaluó con el análisis básico porque el cupo del mes estaba agotado.
            $table->boolean('ia_sin_cupo')->default(false);
            $table->index(['empresa_id', 'ia_cupo_at']);
        });
    }

    public function down(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'ia_cupo_at']);
            $table->dropColumn(['ia_cupo_at', 'ia_sin_cupo']);
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['prueba_hasta', 'plan_solicitado', 'plan_solicitado_at']);
            $table->string('plan', 20)->default('starter')->change();
        });
    }
};

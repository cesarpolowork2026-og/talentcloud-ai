<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Cobro en línea de los planes (App\Pagos): la empresa es el cliente de la
 * pasarela y tiene a lo sumo una suscripción en curso, cuyo estado se copia
 * aquí desde los avisos (webhooks) de la pasarela. eventos_de_pago guarda los
 * avisos ya atendidos que notifican a alguien, para no avisar dos veces si la
 * pasarela repite uno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('pasarela_cliente_id')->nullable()->unique();
            $table->string('pasarela_suscripcion_id')->nullable()->unique();
            $table->string('suscripcion_estado', 20)->nullable();
            $table->string('suscripcion_periodicidad', 10)->nullable();
            $table->timestamp('suscripcion_renueva_at')->nullable();
            $table->boolean('suscripcion_cancela_al_final')->default(false);
        });

        Schema::create('eventos_de_pago', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tipo');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_de_pago');

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'pasarela_cliente_id', 'pasarela_suscripcion_id', 'suscripcion_estado',
                'suscripcion_periodicidad', 'suscripcion_renueva_at', 'suscripcion_cancela_al_final',
            ]);
        });
    }
};

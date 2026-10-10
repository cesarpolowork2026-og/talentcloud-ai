<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Libro de Reclamaciones virtual (Ley 29571 y su reglamento). Cada hoja tiene un
 * número correlativo y se conserva; no pertenece a ninguna empresa cliente (la
 * presenta un consumidor ante TalentCloud AI) y solo la atiende el admin SaaS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reclamaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero')->unique();
            $table->string('tipo', 10);
            $table->string('estado', 15)->default('pendiente');

            // Consumidor.
            $table->string('nombre');
            $table->string('tipo_documento', 10);
            $table->string('numero_documento', 15);
            $table->string('domicilio');
            $table->string('telefono', 20)->nullable();
            $table->string('email');

            // Si es menor de edad: su padre, madre o apoderado.
            $table->boolean('es_menor')->default(false);
            $table->string('apoderado_nombre')->nullable();
            $table->string('apoderado_tipo_documento', 10)->nullable();
            $table->string('apoderado_numero_documento', 15)->nullable();

            // Servicio contratado y monto reclamado (si aplica).
            $table->string('servicio');
            $table->decimal('monto', 10, 2)->nullable();

            $table->text('detalle');
            $table->text('pedido');

            $table->date('fecha_limite');
            $table->text('respuesta')->nullable();
            $table->timestamp('respondida_at')->nullable();
            $table->foreignId('respondida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'fecha_limite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reclamaciones');
    }
};

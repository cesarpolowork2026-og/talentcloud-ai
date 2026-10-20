<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Registro de las acciones del administrador SaaS: quién cambió un plan, suspendió una empresa, tocó el catálogo de
 * competencias o desactivó a un usuario. No es de ninguna empresa (sin empresa_id obligatorio): la lee solo el admin.
 * El nombre de quien actuó se guarda aparte para que el registro siga siendo legible aunque la cuenta se elimine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre', 120);
            $table->string('accion', 40);
            $table->text('descripcion');
            // La empresa sobre la que se actuó, si la hay.
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index('accion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};

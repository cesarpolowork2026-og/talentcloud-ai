<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('invitada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('rol', 20)->default('reclutador');
            // Hash SHA-256 del token: el token en claro solo viaja en el correo.
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expira_at');
            $table->timestamp('aceptada_at')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitaciones');
    }
};

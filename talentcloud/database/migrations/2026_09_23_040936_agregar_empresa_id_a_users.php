<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Empresa del usuario (rol empresa o reclutador). Nulo para el admin SaaS y los postulantes.
            // restrictOnDelete: una empresa con usuarios se suspende, no se borra.
            $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_id');
        });
    }
};

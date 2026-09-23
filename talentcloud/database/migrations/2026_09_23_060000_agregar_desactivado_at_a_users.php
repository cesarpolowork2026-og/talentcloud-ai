<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // La empresa desactiva a un usuario interno: pierde el acceso sin borrar su historial.
            $table->timestamp('desactivado_at')->nullable()->after('notificaciones_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('desactivado_at');
        });
    }
};

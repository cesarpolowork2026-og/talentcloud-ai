<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Extensión unaccent para que los buscadores de los listados ignoren las
 * tildes ("Sofia" encuentra a "Sofía"). Disponible en el contenedor local,
 * en el CI y en Cloud SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create extension if not exists unaccent');
    }

    public function down(): void
    {
        // La extensión se deja instalada: otras bases del servidor pueden usarla.
    }
};

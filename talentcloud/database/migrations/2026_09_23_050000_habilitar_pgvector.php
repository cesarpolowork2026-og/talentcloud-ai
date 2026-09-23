<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/*
 * Extensión pgvector para los embeddings (requisitos y fragmentos de CV).
 * Disponible en el contenedor local, en el CI y en Cloud SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();
    }

    public function down(): void
    {
        // La extensión se deja instalada: otras bases del servidor pueden usarla.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Índices para dos lecturas que ningún índice por empresa cubre:
 *
 * - Bolsa pública (/empleos y portada): vacantes abiertas de todas las empresas,
 *   recientes primero. Un índice parcial (solo abiertas) la deja en un recorrido
 *   corto.
 * - "Actividad reciente" del admin SaaS, sin empresa: ordena toda la tabla por
 *   fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE INDEX vacantes_publicas_index ON vacantes (publicada_at DESC, id) WHERE estado = 'abierta'");
        DB::statement('CREATE INDEX actividades_recientes_index ON actividades (created_at DESC, id DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX vacantes_publicas_index');
        DB::statement('DROP INDEX actividades_recientes_index');
    }
};

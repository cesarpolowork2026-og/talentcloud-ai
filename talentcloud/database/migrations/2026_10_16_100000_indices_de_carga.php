<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Índices para dos lecturas que ningún índice por empresa cubre (auditoría de carga del 15-10-2026):
 *
 * - La bolsa pública (/empleos y la portada) lee las vacantes abiertas de TODAS las empresas, más recientes
 *   primero. Los índices de `vacantes` empiezan por empresa_id: aquí no sirven. Un índice parcial (solo las
 *   abiertas, que son pocas frente al histórico) deja la lectura en un recorrido corto del índice.
 * - La «Actividad reciente» del admin SaaS, sin empresa, ordena toda la tabla por fecha; la de una empresa ya
 *   tiene (empresa_id, created_at).
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

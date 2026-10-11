<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Índices de la auditoría de rendimiento (medidos con 300 000 postulaciones):
 *
 * - Claves foráneas que aún no tenían índice. Al borrar un usuario, un CV o un
 *   requisito, PostgreSQL busca las filas que lo referencian (cascade o set
 *   null): sin índice recorre la tabla entera.
 * - postulaciones (empresa_id, candidato_id): la regla de visibilidad de
 *   candidatos (CandidatoRepository) se resuelve solo con el índice.
 * - postulaciones (empresa_id, created_at): el embudo y los indicadores del
 *   dashboard de una empresa (últimos 90 días).
 * - notifications (notifiable_type, notifiable_id, created_at): la campana lee
 *   las últimas de un usuario ya ordenadas; reemplaza al índice sin la fecha.
 */
return new class extends Migration
{
    /** @var array<string, list<list<string>>> */
    private const INDICES = [
        'vacantes' => [['creada_por']],
        'cvs' => [['subido_por']],
        'postulaciones' => [['cv_id'], ['empresa_id', 'candidato_id'], ['empresa_id', 'created_at']],
        'match_feedback' => [['requisito_id'], ['user_id']],
        'evaluaciones' => [['evaluador_id']],
        'invitaciones' => [['invitada_por']],
        'actividades' => [['actor_id']],
        'notas' => [['autor_id']],
        'reclamaciones' => [['respondida_por']],
        'notifications' => [['notifiable_type', 'notifiable_id', 'created_at']],
    ];

    public function up(): void
    {
        foreach (self::INDICES as $tabla => $indices) {
            Schema::table($tabla, function (Blueprint $table) use ($indices) {
                foreach ($indices as $columnas) {
                    $table->index($columnas);
                }
            });
        }

        // Lo cubre el nuevo índice, que empieza por las mismas columnas.
        Schema::table('notifications', fn (Blueprint $table) => $table->dropIndex(['notifiable_type', 'notifiable_id']));
    }

    public function down(): void
    {
        Schema::table('notifications', fn (Blueprint $table) => $table->index(['notifiable_type', 'notifiable_id']));

        foreach (self::INDICES as $tabla => $indices) {
            Schema::table($tabla, function (Blueprint $table) use ($indices) {
                foreach ($indices as $columnas) {
                    $table->dropIndex($columnas);
                }
            });
        }
    }
};

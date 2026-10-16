<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Aislamiento entre empresas garantizado por la base: una fila hija solo puede
 * apuntar a un padre de su misma empresa. El filtro de la aplicación sigue siendo
 * la primera barrera.
 *
 * Cada clave foránea simple (hija.col -> padre.id) se sustituye por una
 * compuesta (hija.empresa_id, hija.col -> padre.empresa_id, padre.id) con la
 * misma acción al borrar.
 */
return new class extends Migration
{
    /** Padres que necesitan un índice único (empresa_id, id) para ser destino de una clave compuesta. */
    private const PADRES = ['vacantes', 'postulaciones', 'users'];

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}> [hija, columna, padre, acción al borrar el padre]
     */
    private function relaciones(): array
    {
        return [
            ['postulaciones', 'vacante_id', 'vacantes', 'CASCADE'],
            ['requisitos', 'vacante_id', 'vacantes', 'CASCADE'],
            ['entrevistas', 'postulacion_id', 'postulaciones', 'CASCADE'],
            ['evaluaciones', 'postulacion_id', 'postulaciones', 'CASCADE'],
            ['notas', 'postulacion_id', 'postulaciones', 'CASCADE'],
            ['postulacion_etapas', 'postulacion_id', 'postulaciones', 'CASCADE'],
            ['match_feedback', 'postulacion_id', 'postulaciones', 'CASCADE'],
            // Quien entrevista o evalúa pertenece a la empresa de la entrevista o la evaluación.
            ['entrevistas', 'entrevistador_id', 'users', 'RESTRICT'],
            ['evaluaciones', 'evaluador_id', 'users', 'RESTRICT'],
        ];
    }

    public function up(): void
    {
        foreach (self::PADRES as $padre) {
            DB::statement("ALTER TABLE {$padre} ADD CONSTRAINT {$padre}_empresa_id_id_unique UNIQUE (empresa_id, id)");
        }

        foreach ($this->relaciones() as [$hija, $columna, $padre, $accion]) {
            DB::statement("ALTER TABLE {$hija} DROP CONSTRAINT {$hija}_{$columna}_foreign");
            DB::statement(
                "ALTER TABLE {$hija} ADD CONSTRAINT {$hija}_{$columna}_empresa_foreign "
                ."FOREIGN KEY (empresa_id, {$columna}) REFERENCES {$padre} (empresa_id, id) ON DELETE {$accion} NOT VALID"
            );
            DB::statement("ALTER TABLE {$hija} VALIDATE CONSTRAINT {$hija}_{$columna}_empresa_foreign");
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->relaciones()) as [$hija, $columna, $padre, $accion]) {
            DB::statement("ALTER TABLE {$hija} DROP CONSTRAINT {$hija}_{$columna}_empresa_foreign");
            DB::statement("ALTER TABLE {$hija} ADD CONSTRAINT {$hija}_{$columna}_foreign FOREIGN KEY ({$columna}) REFERENCES {$padre} (id) ON DELETE {$accion}");
        }

        foreach (self::PADRES as $padre) {
            DB::statement("ALTER TABLE {$padre} DROP CONSTRAINT {$padre}_empresa_id_id_unique");
        }
    }
};

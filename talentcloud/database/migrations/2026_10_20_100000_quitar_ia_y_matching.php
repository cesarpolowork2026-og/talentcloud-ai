<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Primera unidad: la aplicación gestiona vacantes, candidatos, CV, etapas de selección, entrevistas y evaluaciones; el
 * matching y el análisis con IA quedan para una versión posterior. Esta migración retira lo que solo existía por ellos
 * (resultado del matching y cupo de IA en las postulaciones, fragmentos y vectores de los CV, marcas de resultado
 * incorrecto, competencias extraídas de un CV) y deja el índice del tablero sin la columna de match.
 *
 * Es una migración nueva y no una edición de las anteriores: las bases ya creadas la aplican sin volver a empezar.
 * Con `down()` la estructura vuelve (vacía); los datos retirados no se recuperan.
 */
return new class extends Migration
{
    /** Tipos de actividad que siguen existiendo (sin «cv.analizado»). */
    private const TIPOS_DE_ACTIVIDAD = [
        'postulacion.etapa', 'evaluacion.aprobada', 'entrevista.programada', 'entrevista.cancelada',
        'entrevista.reprogramada', 'postulacion.nota', 'vacante.creada', 'postulacion.propia',
        'postulacion.registrada', 'empresa.plan', 'empresa.plan_solicitado', 'postulacion.retirada',
    ];

    public function up(): void
    {
        // Trabajos y avisos que quedaron de las clases retiradas: sin la clase, fallarían al ejecutarse o al pintarse.
        foreach (['AnalizarCvConIa', 'CalcularMatch', 'GenerarEmbeddingsDeRequisitos'] as $trabajo) {
            DB::table('jobs')->where('payload', 'like', '%'.$trabajo.'%')->delete();
            DB::table('failed_jobs')->where('payload', 'like', '%'.$trabajo.'%')->delete();
        }
        DB::table('notifications')->where('type', 'like', '%AvisoCvAnalizado')->delete();

        // Postulaciones: sin el resultado del matching ni el cupo de IA. Los índices que los nombran se rehacen o se van.
        DB::statement('DROP INDEX IF EXISTS postulaciones_tablero_index');
        DB::statement('DROP INDEX IF EXISTS postulaciones_vacante_match_index');
        DB::statement('DROP INDEX IF EXISTS postulaciones_empresa_id_ia_cupo_at_index');

        Schema::table('postulaciones', function (Blueprint $table) {
            // Las restricciones CHECK que usan estas columnas se van con ellas.
            $table->dropColumn([
                'match_score', 'match_detalle', 'match_motor', 'match_calculado_at', 'ia_cupo_at', 'ia_sin_cupo',
                'requisitos_total', 'requisitos_cubiertos', 'obligatorios_faltantes',
            ]);
        });

        DB::statement(
            'CREATE INDEX postulaciones_tablero_index ON postulaciones (empresa_id, vacante_id, etapa, orden) '
            .'INCLUDE (retirada_at, etapa_cambiada_at, created_at)'
        );

        // Lo que solo servía a la IA: marcas de resultado incorrecto, fragmentos con vectores y los vectores de los requisitos.
        Schema::dropIfExists('match_feedback');
        Schema::dropIfExists('cv_fragmentos');

        Schema::table('requisitos', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'embedding_modelo']);
        });

        Schema::table('cvs', function (Blueprint $table) {
            $table->dropColumn('datos_estructurados');
        });

        // Competencias del candidato: solo las anotadas por una empresa.
        DB::table('candidato_competencia')->where('fuente', 'extraccion')->delete();
        DB::statement('ALTER TABLE candidato_competencia DROP CONSTRAINT IF EXISTS candidato_competencia_fuente_check');
        Schema::table('candidato_competencia', function (Blueprint $table) {
            $table->dropColumn(['anios', 'evidencia']);
        });
        DB::statement("ALTER TABLE candidato_competencia ADD CONSTRAINT candidato_competencia_fuente_check CHECK (fuente IN ('manual'))");

        // Actividad: ya no existe «CV analizado».
        DB::table('actividades')->where('tipo', 'cv.analizado')->delete();
        DB::statement('ALTER TABLE actividades DROP CONSTRAINT IF EXISTS actividades_tipo_check');
        DB::statement("ALTER TABLE actividades ADD CONSTRAINT actividades_tipo_check CHECK (tipo IN ('".implode("', '", self::TIPOS_DE_ACTIVIDAD)."'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE actividades DROP CONSTRAINT IF EXISTS actividades_tipo_check');
        DB::statement("ALTER TABLE actividades ADD CONSTRAINT actividades_tipo_check CHECK (tipo IN ('".implode("', '", [...self::TIPOS_DE_ACTIVIDAD, 'cv.analizado'])."'))");

        DB::statement('ALTER TABLE candidato_competencia DROP CONSTRAINT IF EXISTS candidato_competencia_fuente_check');
        Schema::table('candidato_competencia', function (Blueprint $table) {
            $table->decimal('anios', 4, 1)->nullable();
            $table->text('evidencia')->nullable();
        });
        DB::statement("ALTER TABLE candidato_competencia ADD CONSTRAINT candidato_competencia_fuente_check CHECK (fuente IN ('manual', 'extraccion'))");
        DB::statement('ALTER TABLE candidato_competencia ADD CONSTRAINT candidato_competencia_anios_check CHECK (anios IS NULL OR anios >= 0)');

        Schema::table('cvs', function (Blueprint $table) {
            $table->jsonb('datos_estructurados')->nullable();
        });

        DB::statement('ALTER TABLE requisitos ADD COLUMN embedding vector(384)');
        DB::statement('ALTER TABLE requisitos ADD COLUMN embedding_modelo varchar(120)');

        Schema::create('cv_fragmentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_id')->constrained('cvs')->cascadeOnDelete();
            $table->string('seccion', 40)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->text('texto');
            $table->timestamps();
            $table->index(['cv_id', 'orden']);
        });
        DB::statement('ALTER TABLE cv_fragmentos ADD COLUMN embedding vector(384)');
        DB::statement('ALTER TABLE cv_fragmentos ADD COLUMN embedding_modelo varchar(120)');

        Schema::create('match_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->unsignedBigInteger('postulacion_id');
            $table->foreignId('requisito_id')->constrained('requisitos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comentario')->nullable();
            $table->timestamps();
            $table->unique(['postulacion_id', 'requisito_id', 'user_id']);
            $table->foreign(['empresa_id', 'postulacion_id'], 'match_feedback_postulacion_id_empresa_foreign')
                ->references(['empresa_id', 'id'])->on('postulaciones')->cascadeOnDelete();
        });

        DB::statement('DROP INDEX IF EXISTS postulaciones_tablero_index');
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->decimal('match_score', 5, 2)->nullable();
            $table->jsonb('match_detalle')->nullable();
            $table->string('match_motor', 10)->nullable();
            $table->timestamp('match_calculado_at')->nullable();
            $table->timestamp('ia_cupo_at')->nullable();
            $table->boolean('ia_sin_cupo')->default(false);
            $table->smallInteger('requisitos_total')->nullable();
            $table->smallInteger('requisitos_cubiertos')->nullable();
            $table->smallInteger('obligatorios_faltantes')->nullable();
        });
        DB::statement(
            'CREATE INDEX postulaciones_tablero_index ON postulaciones (empresa_id, vacante_id, etapa, orden) '
            .'INCLUDE (retirada_at, etapa_cambiada_at, created_at, match_score)'
        );
        DB::statement('CREATE INDEX postulaciones_vacante_match_index ON postulaciones (empresa_id, vacante_id, match_score DESC NULLS LAST, id)');
        DB::statement('CREATE INDEX postulaciones_empresa_id_ia_cupo_at_index ON postulaciones (empresa_id, ia_cupo_at)');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Restricciones de dominio (CHECK): la base rechaza valores que la aplicación nunca escribe, de modo que
 * un error de código, un script o una carga masiva no pueden dejar estados inventados ni números fuera de rango.
 *
 * Las listas de valores son una copia fija de los enums de `app/Enums`: si se añade un caso a un enum hay que
 * añadir una migración que actualice su restricción (lo vigila `tests/Feature/Datos/RestriccionesDeDominioTest`).
 *
 * Cada restricción se crea NOT VALID y se valida después: la validación solo toma un bloqueo que no impide
 * leer ni escribir, así que también sirve sobre tablas grandes.
 */
return new class extends Migration
{
    /**
     * @return array<string, array<string, string>> tabla => [restricción => condición]
     */
    private function reglas(): array
    {
        $en = fn (string $columna, array $valores, bool $admiteNulo = false): string => ($admiteNulo ? "{$columna} IS NULL OR " : '')
            ."{$columna} IN ('".implode("', '", $valores)."')";

        $etapas = ['postulado', 'en_revision', 'entrevista', 'oferta', 'contratado', 'descartado'];
        $planes = ['gratis', 'starter', 'pro', 'enterprise'];
        $documentos = ['dni', 'ce', 'pasaporte'];

        return [
            'vacantes' => [
                'vacantes_estado_check' => $en('estado', ['borrador', 'abierta', 'en_revision', 'cerrada']),
                'vacantes_modalidad_check' => $en('modalidad', ['remoto', 'presencial', 'hibrido']),
                // En remoto la ubicación es una cobertura opcional; en presencial o híbrido hay que decir dónde.
                'vacantes_ubicacion_check' => "modalidad = 'remoto' OR (ubicacion IS NOT NULL AND btrim(ubicacion) <> '')",
            ],
            'requisitos' => [
                'requisitos_tipo_check' => $en('tipo', ['obligatorio', 'deseable']),
                'requisitos_peso_check' => 'peso BETWEEN 1 AND 5',
                'requisitos_anios_minimos_check' => 'anios_minimos IS NULL OR anios_minimos BETWEEN 0 AND 40',
            ],
            'postulaciones' => [
                'postulaciones_etapa_check' => $en('etapa', $etapas),
                'postulaciones_origen_check' => $en('origen', ['equipo', 'postulante']),
                'postulaciones_match_motor_check' => $en('match_motor', ['http', 'fake'], true),
                'postulaciones_estado_avisado_check' => $en('estado_avisado', ['postulado', 'en_revision', 'entrevista', 'contratado', 'no_seleccionado', 'cerrada', 'retirada'], true),
                'postulaciones_match_score_check' => 'match_score IS NULL OR match_score BETWEEN 0 AND 100',
                'postulaciones_orden_check' => 'orden >= 0',
                // «Retirada ⇒ Descartado» no es una restricción de la base: la aplicación fija `retirada_at` y después
                // mueve la postulación (dos pasos), y un CHECK no se puede diferir hasta el final de la transacción.
                'postulaciones_resumen_check' => 'requisitos_total IS NULL OR (requisitos_total >= 0 AND requisitos_cubiertos BETWEEN 0 AND requisitos_total AND obligatorios_faltantes BETWEEN 0 AND requisitos_total)',
            ],
            'postulacion_etapas' => [
                'postulacion_etapas_etapa_check' => $en('etapa', $etapas),
            ],
            'entrevistas' => [
                'entrevistas_estado_check' => $en('estado', ['pendiente', 'confirmada', 'completada', 'cancelada']),
                'entrevistas_modalidad_check' => $en('modalidad', ['videollamada', 'presencial', 'telefonica']),
                'entrevistas_duracion_check' => 'duracion_min > 0',
            ],
            'evaluaciones' => [
                'evaluaciones_estado_check' => $en('estado', ['pendiente', 'en_revision', 'aprobado', 'rechazado']),
                'evaluaciones_puntaje_check' => 'puntaje IS NULL OR puntaje BETWEEN 0 AND 10',
            ],
            'empresas' => [
                'empresas_estado_check' => $en('estado', ['activa', 'suspendida']),
                'empresas_plan_check' => $en('plan', $planes),
                'empresas_plan_solicitado_check' => $en('plan_solicitado', $planes, true),
                'empresas_suscripcion_estado_check' => $en('suscripcion_estado', ['activa', 'pago_pendiente', 'incompleta', 'terminada'], true),
                'empresas_suscripcion_periodicidad_check' => $en('suscripcion_periodicidad', ['mensual', 'anual'], true),
            ],
            'invitaciones' => [
                'invitaciones_rol_check' => $en('rol', ['admin', 'empresa', 'reclutador', 'postulante']),
            ],
            'candidato_competencia' => [
                'candidato_competencia_fuente_check' => $en('fuente', ['manual', 'extraccion']),
                'candidato_competencia_anios_check' => 'anios IS NULL OR anios >= 0',
            ],
            'cvs' => [
                'cvs_estado_procesamiento_check' => $en('estado_procesamiento', ['pendiente', 'procesando', 'procesado', 'error']),
                'cvs_tamano_check' => 'tamano >= 0',
            ],
            'reclamaciones' => [
                'reclamaciones_tipo_check' => $en('tipo', ['reclamo', 'queja']),
                'reclamaciones_estado_check' => $en('estado', ['pendiente', 'respondida']),
                'reclamaciones_tipo_documento_check' => $en('tipo_documento', $documentos),
                'reclamaciones_apoderado_tipo_documento_check' => $en('apoderado_tipo_documento', $documentos, true),
                'reclamaciones_monto_check' => 'monto IS NULL OR monto >= 0',
            ],
            'actividades' => [
                'actividades_tipo_check' => $en('tipo', [
                    'postulacion.etapa', 'cv.analizado', 'evaluacion.aprobada', 'entrevista.programada', 'entrevista.cancelada',
                    'entrevista.reprogramada', 'postulacion.nota', 'vacante.creada', 'postulacion.propia', 'postulacion.registrada',
                    'empresa.plan', 'empresa.plan_solicitado', 'postulacion.retirada',
                ]),
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->reglas() as $tabla => $restricciones) {
            foreach ($restricciones as $nombre => $condicion) {
                DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$nombre} CHECK ({$condicion}) NOT VALID");
                DB::statement("ALTER TABLE {$tabla} VALIDATE CONSTRAINT {$nombre}");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->reglas() as $tabla => $restricciones) {
            foreach (array_keys($restricciones) as $nombre) {
                DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT IF EXISTS {$nombre}");
            }
        }
    }
};

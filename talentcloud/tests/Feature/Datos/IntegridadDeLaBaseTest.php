<?php

use App\Enums\EstadoDeMiPostulacion;
use App\Enums\EstadoEmpresa;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoEvaluacion;
use App\Enums\EstadoProcesamientoCv;
use App\Enums\EstadoReclamacion;
use App\Enums\EstadoSuscripcion;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\FuenteCompetencia;
use App\Enums\Modalidad;
use App\Enums\ModalidadEntrevista;
use App\Enums\OrigenPostulacion;
use App\Enums\Periodicidad;
use App\Enums\PlanEmpresa;
use App\Enums\Rol;
use App\Enums\TipoActividad;
use App\Enums\TipoDocumento;
use App\Enums\TipoReclamacion;
use App\Enums\TipoRequisito;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;

/*
 * Lo que la base garantiza por sí sola, sin depender de la aplicación:
 *  - los estados y etapas son siempre los de los enums (y las restricciones no se quedan atrás si se añade un caso);
 *  - los números están en rango;
 *  - una fila hija es de la misma empresa que su padre;
 *  - las claves compuestas conservan el comportamiento al borrar (cascada o restricción).
 */

const CHECK = '23514';
const CLAVE_FORANEA = '23503';

/** Ejecuta la acción y exige que la base la rechace con ese código SQLSTATE; devuelve el mensaje. */
function rechazadaPorLaBase(Closure $accion, string $codigo): string
{
    try {
        DB::transaction($accion); // un punto de guardado: la prueba sigue usable después del error
    } catch (QueryException $e) {
        expect($e->getCode())->toBe($codigo);

        return $e->getMessage();
    }

    throw new AssertionFailedError('La base aceptó un dato que debía rechazar.');
}

beforeEach(function () {
    $this->nimbus = Empresa::factory()->create();
    $this->vela = Empresa::factory()->create();
    $this->backend = Vacante::factory()->deEmpresa($this->nimbus)->enEstado(EstadoVacante::Abierta)->create();
    $this->postulacion = Postulacion::factory()->paraVacante($this->backend)->create();
});

// — Los CHECK de los estados siguen a los enums —

dataset('enums de la base', [
    'vacantes.estado' => ['vacantes', 'estado', EstadoVacante::class],
    'vacantes.modalidad' => ['vacantes', 'modalidad', Modalidad::class],
    'requisitos.tipo' => ['requisitos', 'tipo', TipoRequisito::class],
    'postulaciones.etapa' => ['postulaciones', 'etapa', EtapaPostulacion::class],
    'postulaciones.origen' => ['postulaciones', 'origen', OrigenPostulacion::class],
    'postulaciones.estado_avisado' => ['postulaciones', 'estado_avisado', EstadoDeMiPostulacion::class],
    'postulacion_etapas.etapa' => ['postulacion_etapas', 'etapa', EtapaPostulacion::class],
    'entrevistas.estado' => ['entrevistas', 'estado', EstadoEntrevista::class],
    'entrevistas.modalidad' => ['entrevistas', 'modalidad', ModalidadEntrevista::class],
    'evaluaciones.estado' => ['evaluaciones', 'estado', EstadoEvaluacion::class],
    'empresas.estado' => ['empresas', 'estado', EstadoEmpresa::class],
    'empresas.plan' => ['empresas', 'plan', PlanEmpresa::class],
    'empresas.plan_solicitado' => ['empresas', 'plan_solicitado', PlanEmpresa::class],
    'empresas.suscripcion_estado' => ['empresas', 'suscripcion_estado', EstadoSuscripcion::class],
    'empresas.suscripcion_periodicidad' => ['empresas', 'suscripcion_periodicidad', Periodicidad::class],
    'invitaciones.rol' => ['invitaciones', 'rol', Rol::class],
    'candidato_competencia.fuente' => ['candidato_competencia', 'fuente', FuenteCompetencia::class],
    'cvs.estado_procesamiento' => ['cvs', 'estado_procesamiento', EstadoProcesamientoCv::class],
    'reclamaciones.tipo' => ['reclamaciones', 'tipo', TipoReclamacion::class],
    'reclamaciones.estado' => ['reclamaciones', 'estado', EstadoReclamacion::class],
    'reclamaciones.tipo_documento' => ['reclamaciones', 'tipo_documento', TipoDocumento::class],
    'reclamaciones.apoderado_tipo_documento' => ['reclamaciones', 'apoderado_tipo_documento', TipoDocumento::class],
    'actividades.tipo' => ['actividades', 'tipo', TipoActividad::class],
]);

it('la restricción de cada columna admite exactamente los valores de su enum', function (string $tabla, string $columna, string $enum) {
    $definicion = DB::scalar(
        'SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conname = ? AND contype = ?',
        ["{$tabla}_{$columna}_check", 'c'],
    );

    expect($definicion)->not->toBeNull("Falta la restricción {$tabla}_{$columna}_check");

    preg_match_all("/'([^']+)'::/", $definicion, $coincidencias);

    $enLaBase = $coincidencias[1];
    $enElCodigo = array_map(fn (BackedEnum $caso) => $caso->value, $enum::cases());
    sort($enLaBase);
    sort($enElCodigo);

    // Si falla: se añadió (o quitó) un caso en el enum sin una migración que actualice esta restricción.
    expect($enLaBase)->toBe($enElCodigo);
})->with('enums de la base');

it('rechaza un estado o una etapa que no existe', function () {
    rechazadaPorLaBase(fn () => DB::table('postulaciones')->where('id', $this->postulacion->id)->update(['etapa' => 'inventada']), CHECK);
    rechazadaPorLaBase(fn () => DB::table('vacantes')->where('id', $this->backend->id)->update(['estado' => 'publicada']), CHECK);
    rechazadaPorLaBase(fn () => DB::table('empresas')->where('id', $this->nimbus->id)->update(['plan' => 'platino']), CHECK);

    expect(DB::table('postulaciones')->where('id', $this->postulacion->id)->value('etapa'))->not->toBe('inventada');
});

// — Rangos y reglas de los valores —

it('rechaza números fuera de rango', function () {
    rechazadaPorLaBase(fn () => DB::table('postulaciones')->where('id', $this->postulacion->id)->update(['orden' => -3]), CHECK);

    $entrevista = Entrevista::factory()->create(['postulacion_id' => $this->postulacion->id]);
    rechazadaPorLaBase(fn () => DB::table('entrevistas')->where('id', $entrevista->id)->update(['duracion_min' => 0]), CHECK);

    $requisito = Requisito::factory()->create(['vacante_id' => $this->backend->id]);
    rechazadaPorLaBase(fn () => DB::table('requisitos')->where('id', $requisito->id)->update(['peso' => 6]), CHECK);
    rechazadaPorLaBase(fn () => DB::table('requisitos')->where('id', $requisito->id)->update(['anios_minimos' => 41]), CHECK);
});

it('acepta los extremos válidos', function () {
    $requisito = Requisito::factory()->create(['vacante_id' => $this->backend->id]);
    DB::table('requisitos')->where('id', $requisito->id)->update(['peso' => 5, 'anios_minimos' => 40]);
    DB::table('postulaciones')->where('id', $this->postulacion->id)->update(['orden' => 0]);

    expect(DB::table('requisitos')->where('id', $requisito->id)->value('peso'))->toBe(5);
});

it('una vacante presencial o híbrida necesita ubicación; en remoto es opcional', function () {
    DB::table('vacantes')->where('id', $this->backend->id)->update(['modalidad' => 'remoto', 'ubicacion' => null]);

    rechazadaPorLaBase(fn () => DB::table('vacantes')->where('id', $this->backend->id)->update(['modalidad' => 'presencial', 'ubicacion' => null]), CHECK);
    rechazadaPorLaBase(fn () => DB::table('vacantes')->where('id', $this->backend->id)->update(['modalidad' => 'hibrido', 'ubicacion' => '   ']), CHECK);

    DB::table('vacantes')->where('id', $this->backend->id)->update(['modalidad' => 'presencial', 'ubicacion' => 'Lima']);
    expect(DB::table('vacantes')->where('id', $this->backend->id)->value('modalidad'))->toBe('presencial');
});

// — Una fila hija es de la misma empresa que su padre —

it('no deja postular a una vacante de otra empresa', function () {
    $mensaje = rechazadaPorLaBase(function () {
        app(TenantContext::class)->ejecutarGlobal(fn () => Postulacion::factory()->paraVacante($this->backend)->create(['empresa_id' => $this->vela->id]));
    }, CLAVE_FORANEA);

    expect($mensaje)->toContain('postulaciones_vacante_id_empresa_foreign');
});

it('no deja colgar una entrevista, una nota o un historial de la postulación de otra empresa', function () {
    $entrevistador = User::factory()->reclutador()->create(['empresa_id' => $this->vela->id]);

    $mensaje = rechazadaPorLaBase(fn () => DB::table('entrevistas')->insert([
        'empresa_id' => $this->vela->id, 'postulacion_id' => $this->postulacion->id, 'entrevistador_id' => $entrevistador->id,
        'inicia_at' => now()->addDay(), 'duracion_min' => 45, 'modalidad' => 'videollamada', 'estado' => 'pendiente',
    ]), CLAVE_FORANEA);
    expect($mensaje)->toContain('entrevistas_postulacion_id_empresa_foreign');

    $mensaje = rechazadaPorLaBase(fn () => DB::table('postulacion_etapas')->insert([
        'empresa_id' => $this->vela->id, 'postulacion_id' => $this->postulacion->id, 'etapa' => 'postulado', 'desde' => now(),
    ]), CLAVE_FORANEA);
    expect($mensaje)->toContain('postulacion_etapas_postulacion_id_empresa_foreign');

    $mensaje = rechazadaPorLaBase(fn () => DB::table('notas')->insert([
        'empresa_id' => $this->vela->id, 'postulacion_id' => $this->postulacion->id, 'texto' => 'No debería entrar.', 'created_at' => now(), 'updated_at' => now(),
    ]), CLAVE_FORANEA);
    expect($mensaje)->toContain('notas_postulacion_id_empresa_foreign');
});

it('no deja que entreviste o evalúe alguien de otra empresa', function () {
    $deOtraEmpresa = User::factory()->reclutador()->create(['empresa_id' => $this->vela->id]);

    $mensaje = rechazadaPorLaBase(fn () => Entrevista::factory()->create([
        'postulacion_id' => $this->postulacion->id, 'entrevistador_id' => $deOtraEmpresa->id,
    ]), CLAVE_FORANEA);
    expect($mensaje)->toContain('entrevistas_entrevistador_id_empresa_foreign');

    $mensaje = rechazadaPorLaBase(fn () => DB::table('evaluaciones')->insert([
        'empresa_id' => $this->nimbus->id, 'postulacion_id' => $this->postulacion->id, 'evaluador_id' => $deOtraEmpresa->id,
        'criterios' => '[]', 'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now(),
    ]), CLAVE_FORANEA);
    expect($mensaje)->toContain('evaluaciones_evaluador_id_empresa_foreign');
});

it('no deja un requisito en la vacante de otra empresa', function () {
    $mensaje = rechazadaPorLaBase(fn () => Requisito::factory()->create(['vacante_id' => $this->backend->id, 'empresa_id' => $this->vela->id]), CLAVE_FORANEA);

    expect($mensaje)->toContain('requisitos_vacante_id_empresa_foreign');
});

// — El comportamiento al borrar no cambió —

it('al borrar una vacante se borran en cascada sus postulaciones, historial, entrevistas y requisitos', function () {
    $entrevista = Entrevista::factory()->create(['postulacion_id' => $this->postulacion->id]);
    $requisito = Requisito::factory()->create(['vacante_id' => $this->backend->id]);

    expect(DB::table('postulacion_etapas')->where('postulacion_id', $this->postulacion->id)->exists())->toBeTrue();

    DB::table('vacantes')->where('id', $this->backend->id)->delete();

    expect(DB::table('postulaciones')->where('id', $this->postulacion->id)->exists())->toBeFalse()
        ->and(DB::table('postulacion_etapas')->where('postulacion_id', $this->postulacion->id)->exists())->toBeFalse()
        ->and(DB::table('entrevistas')->where('id', $entrevista->id)->exists())->toBeFalse()
        ->and(DB::table('requisitos')->where('id', $requisito->id)->exists())->toBeFalse();
});

it('no deja borrar a quien tiene entrevistas: primero se reasignan', function () {
    $entrevista = Entrevista::factory()->create(['postulacion_id' => $this->postulacion->id]);

    rechazadaPorLaBase(fn () => DB::table('users')->where('id', $entrevista->entrevistador_id)->delete(), CLAVE_FORANEA);
});

it('el índice del tablero cubre las columnas que lee el dashboard', function () {
    $definicion = DB::scalar("SELECT indexdef FROM pg_indexes WHERE indexname = 'postulaciones_tablero_index'");

    expect($definicion)->not->toBeNull()
        ->and($definicion)->toContain('(empresa_id, vacante_id, etapa, orden)')
        ->and($definicion)->toContain('INCLUDE (retirada_at, etapa_cambiada_at, created_at)')
        ->and(DB::scalar("SELECT 1 FROM pg_indexes WHERE indexname = 'postulaciones_empresa_id_vacante_id_etapa_orden_index'"))->toBeNull();
});

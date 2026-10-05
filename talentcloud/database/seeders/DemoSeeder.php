<?php

namespace Database\Seeders;

use App\Enums\CriterioEvaluacion;
use App\Enums\EstadoEmpresa;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoEvaluacion;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\FuenteCompetencia;
use App\Enums\Modalidad;
use App\Enums\ModalidadEntrevista;
use App\Enums\OrigenPostulacion;
use App\Enums\PlanEmpresa;
use App\Enums\Rol;
use App\Enums\TipoActividad;
use App\Enums\TipoRequisito;
use App\Jobs\CalcularMatch;
use App\Jobs\ExtraerTextoCv;
use App\Models\Actividad;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Evaluacion;
use App\Models\Invitacion;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Notifications\AvisoCvAnalizado;
use App\Notifications\AvisoNuevaPostulacion;
use App\Services\Cvs\SubirCv;
use App\Support\Formato;
use App\Tenancy\TenantContext;
use Database\Seeders\Demo\CvsDeDemostracion;
use Database\Seeders\Demo\DocumentosDePrueba;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Datos de demostración con las personas y vacantes del prototipo
 * (TalentCloud App.dc.html). Todas las cuentas usan la contraseña "password".
 *
 * Coherentes con un proceso real (a diferencia de los datos ilustrativos del
 * prototipo): nadie está en una etapa anterior a lo que ya le pasó (quien tuvo
 * entrevista está en "Entrevista" o más allá; no hay oferta sin entrevista ni
 * evaluación sin entrevista realizada), nadie postula a un borrador ni antes
 * de que exista la vacante, y cada empresa solo recibe avisos de lo suyo.
 *
 * Las fechas son relativas al día en que se siembra: lo pasado está
 * completado y lo futuro, pendiente o confirmado, sea cual sea el día de la
 * demostración.
 *
 * Los CV son archivos reales generados al sembrar y el matching se calcula
 * con el motor configurado (MATCHING_ENGINE): los porcentajes salen de la
 * evidencia de cada CV, no de los números ilustrativos del prototipo.
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /** @var array<string, Competencia> */
    private array $competencias = [];

    public function run(): void
    {
        app(TenantContext::class)->ejecutarGlobal(fn () => $this->sembrar());
    }

    private function sembrar(): void
    {
        $this->competencias = Competencia::all()->keyBy('nombre')->all();

        // — Empresas (pantalla "Empresas" del admin) —
        $nimbus = $this->empresa('Nimbus Corp', PlanEmpresa::Enterprise, EstadoEmpresa::Activa, 'Tecnología', ['perfil' => true, 'vacante' => true, 'invitar' => true]);
        $vela = $this->empresa('Vela Studio', PlanEmpresa::Pro, EstadoEmpresa::Activa, 'Diseño', ['perfil' => true, 'vacante' => true, 'invitar' => false]);
        $orbita = $this->empresa('Orbita Labs', PlanEmpresa::Starter, EstadoEmpresa::Suspendida, 'Tecnología', ['perfil' => true, 'vacante' => false, 'invitar' => false]);

        // — Cuentas —
        $this->usuario('Administrador', 'admin@talentcloud.ai', Rol::Admin);
        $this->usuario('Nimbus Corp — Admin', 'admin@nimbus.com', Rol::Empresa, $nimbus);
        $marta = $this->usuario('Marta Solís', 'marta@nimbus.com', Rol::Reclutador, $nimbus);
        $diego = $this->usuario('Diego Ferro', 'diego@nimbus.com', Rol::Reclutador, $nimbus);
        $this->usuario('Vela Studio — Admin', 'admin@vela.studio', Rol::Empresa, $vela);
        $clara = $this->usuario('Clara Vidal', 'clara@vela.studio', Rol::Reclutador, $vela);
        $this->usuario('Orbita Labs — Admin', 'admin@orbita.dev', Rol::Empresa, $orbita);
        $marcoUsuario = $this->usuario('Marco Díaz', 'marco@correo.com', Rol::Postulante);

        // "Ana López — Administradora — Invitado" de Mi empresa.
        $invitacion = Invitacion::firstOrNew(['email' => 'ana.lopez@nimbus.com']);
        $invitacion->fill(['nombre' => 'Ana López', 'rol' => Rol::Empresa, 'token_hash' => hash('sha256', Str::random(40)), 'expira_at' => now()->addDays(7)]);
        $invitacion->empresa_id = $nimbus->id;
        $invitacion->save();

        $hoy = Carbon::today();

        // — Vacantes (pantalla "Vacantes"), creadas hace N días —
        $backend = $this->vacante($nimbus, 'Desarrollador Backend', 'Remoto', Modalidad::Remoto, EstadoVacante::Abierta, 32, $marta,
            'Buscamos una persona para diseñar y mantener APIs REST de alto tráfico con Java y Spring Boot.', [
                ['Java', TipoRequisito::Obligatorio, 5, 3],
                ['Spring Boot', TipoRequisito::Obligatorio, 4, 2],
                ['PostgreSQL', TipoRequisito::Obligatorio, 4, null],
                ['Docker', TipoRequisito::Deseable, 3, null],
                ['Kubernetes', TipoRequisito::Deseable, 2, null],
                ['Certificación AWS', TipoRequisito::Deseable, 2, null],
            ]);
        $designer = $this->vacante($vela, 'Product Designer', 'Madrid', Modalidad::Presencial, EstadoVacante::Abierta, 29, $clara,
            'Diseño de producto de punta a punta: investigación, prototipado y sistema de diseño.', [
                ['Figma', TipoRequisito::Obligatorio, 5, 2],
                ['Research', TipoRequisito::Obligatorio, 4, null],
                ['Prototipado', TipoRequisito::Deseable, 3, null],
            ]);
        $dataAnalyst = $this->vacante($nimbus, 'Data Analyst', 'Remoto', Modalidad::Remoto, EstadoVacante::EnRevision, 24, $diego,
            'Análisis de datos de negocio y construcción de tableros para las áreas comerciales.', [
                ['SQL', TipoRequisito::Obligatorio, 5, 2],
                ['Python', TipoRequisito::Obligatorio, 4, null],
                ['Power BI', TipoRequisito::Deseable, 3, null],
            ]);
        // Borrador de una empresa suspendida: sin publicar, nadie puede postular.
        $this->vacante($orbita, 'QA Engineer', 'Bogotá', Modalidad::Hibrido, EstadoVacante::Borrador, 20, null,
            'Automatización de pruebas web y de APIs.', [
                ['Selenium', TipoRequisito::Obligatorio, 4, null],
                ['Cypress', TipoRequisito::Deseable, 3, null],
                ['Testing de APIs', TipoRequisito::Obligatorio, 4, null],
            ]);
        // Cerrada 15 días después de publicarse (hace 25 días).
        $devops = $this->vacante($vela, 'DevOps Engineer', 'Remoto', Modalidad::Remoto, EstadoVacante::Cerrada, 40, null,
            'Infraestructura como código y despliegues continuos en la nube.', [
                ['Docker', TipoRequisito::Obligatorio, 4, null],
                ['Kubernetes', TipoRequisito::Obligatorio, 4, null],
                ['Terraform', TipoRequisito::Deseable, 3, null],
                ['AWS', TipoRequisito::Obligatorio, 4, null],
            ]);

        // — Candidatos (pantalla "Candidatos") con sus competencias —
        $elena = $this->candidato('Elena Ruiz', 'elena.ruiz@correo.com', 'Desarrolladora Backend', $nimbus, ['Java', 'Spring Boot', 'PostgreSQL']);
        $sofia = $this->candidato('Sofía Nieto', 'sofia.nieto@correo.com', 'Desarrolladora Backend', $nimbus, ['Java', 'Docker', 'Kubernetes']);
        $marco = $this->candidato('Marco Díaz', 'marco@correo.com', 'Desarrollador Backend', null, ['Java', 'MySQL'], $marcoUsuario, anotadasPor: $nimbus);
        $ivan = $this->candidato('Iván Torres', 'ivan.torres@correo.com', 'Diseñador de Producto', $vela, ['Figma', 'Research']);
        $lucia = $this->candidato('Lucía Prat', 'lucia.prat@correo.com', 'Analista de Datos', $nimbus, ['SQL', 'Python']);
        $pablo = $this->candidato('Pablo Vega', 'pablo.vega@correo.com', 'QA Engineer', $nimbus, ['Selenium']);

        // — Postulaciones (hace N días) y etapa actual (desde hace M días) —
        // Desarrollador Backend: Marco recién llegado; Elena entre la 1.ª y la 2.ª entrevista;
        // Sofía con oferta; Pablo descartado en la revisión del CV (no se pudo leer).
        $pMarco = $this->postulacion($backend, $marco, EtapaPostulacion::Postulado, 0, 12, 12, OrigenPostulacion::Postulante);
        $pElena = $this->postulacion($backend, $elena, EtapaPostulacion::Entrevista, 0, 31, 9);
        $pSofia = $this->postulacion($backend, $sofia, EtapaPostulacion::Oferta, 0, 30, 3);
        $this->postulacion($backend, $pablo, EtapaPostulacion::Descartado, 0, 29, 27);

        $pIvan = $this->postulacion($designer, $ivan, EtapaPostulacion::Entrevista, 0, 27, 2);
        $pLucia = $this->postulacion($dataAnalyst, $lucia, EtapaPostulacion::Oferta, 0, 23, 4);
        // Postuló dos días después de publicarse y quedó fuera antes del cierre.
        $this->postulacion($devops, $marco, EtapaPostulacion::Descartado, 0, 38, 30, OrigenPostulacion::Postulante);

        // — Entrevistas (pantalla "Entrevistas"): las pasadas, realizadas; las próximas, por confirmar o confirmadas —
        $this->entrevista($pElena, $marta, $hoy->copy()->subDays(6)->setTime(10, 0), EstadoEntrevista::Completada);
        $entrevistaElena = $this->entrevista($pElena, $diego, $hoy->copy()->addDays(2)->setTime(15, 0), EstadoEntrevista::Pendiente);
        $this->entrevista($pSofia, $marta, $hoy->copy()->subDays(10)->setTime(11, 0), EstadoEntrevista::Completada);
        $this->entrevista($pLucia, $marta, $hoy->copy()->subDays(15)->setTime(11, 0), EstadoEntrevista::Cancelada);
        $this->entrevista($pLucia, $diego, $hoy->copy()->subDays(13)->setTime(9, 30), EstadoEntrevista::Completada);
        $this->entrevista($pIvan, $clara, $hoy->copy()->addDays(3)->setTime(10, 0), EstadoEntrevista::Confirmada);

        // — Evaluaciones (pantalla "Evaluaciones"): solo tras una entrevista realizada —
        $this->evaluacion($pElena, $marta, [9, 8, 9, 8.4], EstadoEvaluacion::Aprobado);
        $this->evaluacion($pSofia, $marta, [9, 8.5, 9, 9], EstadoEvaluacion::Aprobado);
        $this->evaluacion($pLucia, $diego, [8, 8, 7.6, 8], EstadoEvaluacion::Aprobado);

        // — CVs (archivos reales en el disco privado, procesados al sembrar) —
        $this->sembrarCvs([$elena, $sofia, $marco, $ivan, $lucia, $pablo], $marta);

        // — Matching real de todas las postulaciones, calculado con el motor configurado —
        Postulacion::sinFiltroDeEmpresa()->get(['id', 'empresa_id'])
            ->each(fn (Postulacion $p) => CalcularMatch::dispatchSync($p->empresa_id, $p->id));

        // — Actividad reciente y notificaciones: los mismos textos que genera la aplicación —
        // Minutos atrás: el dashboard muestra lo más reciente primero.
        $this->actividad($nimbus, $marta, TipoActividad::EtapaCambiada, 'Sofía Nieto avanzó a la etapa de oferta.', 10);
        $this->actividad($nimbus, $diego, TipoActividad::EntrevistaProgramada, 'Diego Ferro programó una entrevista con Elena Ruiz para el '.Formato::fechaHora($entrevistaElena->inicia_at).'.', 20);
        $this->actividad($nimbus, $marta, TipoActividad::EvaluacionAprobada, 'Marta Solís aprobó la evaluación de Elena Ruiz.', 30);
        $this->actividad($nimbus, null, TipoActividad::CandidatoSePostulo, 'Marco Díaz postuló a Desarrollador Backend.', 40, $pMarco);
        $this->actividad($orbita, null, TipoActividad::VacanteCreada, 'Orbita Labs creó la vacante QA Engineer.', 50);

        // Campana de Marta: solo lo que la aplicación le avisaría a ella. Marco postuló por su
        // cuenta a una vacante que ella creó; los CV de Elena y Sofía los subió ella.
        if ($marta->notifications()->doesntExist()) {
            $this->aviso($marta, AvisoNuevaPostulacion::de('Marco Díaz', 'Desarrollador Backend', route('matching.show', $pMarco), $marco->id), 5);
            $this->aviso($marta, AvisoCvAnalizado::paraReclutador('Elena Ruiz', $this->extraidas($elena), route('candidatos.show', $elena), $elena->id), 15);
            $this->aviso($marta, AvisoCvAnalizado::paraReclutador('Sofía Nieto', $this->extraidas($sofia), route('candidatos.show', $sofia), $sofia->id), 25);
        }
    }

    /**
     * @param  array<string, bool>  $onboarding
     */
    private function empresa(string $nombre, PlanEmpresa $plan, EstadoEmpresa $estado, string $sector, array $onboarding): Empresa
    {
        return Empresa::updateOrCreate(
            ['nombre' => $nombre],
            ['plan' => $plan, 'estado' => $estado, 'sector' => $sector, 'onboarding' => $onboarding],
        );
    }

    private function usuario(string $nombre, string $email, Rol $rol, ?Empresa $empresa = null): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $nombre,
            'password' => self::PASSWORD,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'empresa_id' => $empresa?->id,
        ])->save();

        $user->syncRoles([$rol->value]);

        return $user;
    }

    /**
     * @param  list<array{0: string, 1: TipoRequisito, 2: int, 3: int|null}>  $requisitos
     */
    private function vacante(Empresa $empresa, string $titulo, string $ubicacion, Modalidad $modalidad, EstadoVacante $estado, int $haceDias, ?User $creador, string $descripcion, array $requisitos): Vacante
    {
        $fecha = Carbon::today()->subDays($haceDias)->setTime(9, 0);

        $vacante = Vacante::firstOrNew(['empresa_id' => $empresa->id, 'titulo' => $titulo]);
        $vacante->fill([
            'descripcion' => $descripcion,
            'ubicacion' => $ubicacion,
            'modalidad' => $modalidad,
            'estado' => $estado,
            'publicada_at' => $estado === EstadoVacante::Borrador ? null : $fecha,
            'cerrada_at' => $estado === EstadoVacante::Cerrada ? $fecha->copy()->addDays(15) : null,
        ]);
        $vacante->empresa_id = $empresa->id;
        $vacante->creada_por = $creador?->id;
        $vacante->created_at = $fecha;
        $vacante->save();

        foreach ($requisitos as $orden => [$competencia, $tipo, $peso, $anios]) {
            $requisito = Requisito::firstOrNew(['vacante_id' => $vacante->id, 'competencia_id' => $this->competencia($competencia)->id]);
            $requisito->fill(['tipo' => $tipo, 'peso' => $peso, 'anios_minimos' => $anios, 'orden' => $orden]);
            $requisito->empresa_id = $empresa->id;
            $requisito->save();
        }

        return $vacante;
    }

    /**
     * @param  list<string>  $competencias  competencias manuales, anotadas por la empresa de origen (o por $anotadasPor)
     */
    private function candidato(string $nombre, string $email, string $titular, ?Empresa $origen, array $competencias, ?User $usuario = null, ?Empresa $anotadasPor = null): Candidato
    {
        $candidato = Candidato::updateOrCreate(
            ['email' => $email],
            [
                'nombre' => $nombre,
                'titular' => $titular,
                'user_id' => $usuario?->id,
                'empresa_origen_id' => $origen?->id,
                'consentimiento_at' => now(),
            ],
        );

        $candidato->todasLasCompetencias()
            ->withPivotValue('fuente', FuenteCompetencia::Manual->value)
            ->withPivotValue('empresa_id', ($anotadasPor ?? $origen)?->id)
            ->sync(array_map(fn (string $c) => $this->competencia($c)->id, $competencias));

        return $candidato;
    }

    /**
     * Postulación de hace $haceDias, en su etapa actual desde hace $etapaHaceDias.
     */
    private function postulacion(Vacante $vacante, Candidato $candidato, EtapaPostulacion $etapa, int $orden, int $haceDias, int $etapaHaceDias, OrigenPostulacion $origen = OrigenPostulacion::Equipo): Postulacion
    {
        $postulacion = Postulacion::firstOrNew(['vacante_id' => $vacante->id, 'candidato_id' => $candidato->id]);
        $postulacion->fill([
            'etapa' => $etapa,
            'orden' => $orden,
            'etapa_cambiada_at' => Carbon::today()->subDays($etapaHaceDias)->setTime(12, 0),
            'origen' => $origen,
        ]);
        $postulacion->empresa_id = $vacante->empresa_id;
        $postulacion->created_at = Carbon::today()->subDays($haceDias)->setTime(12, 0);
        $postulacion->save();

        return $postulacion;
    }

    private function entrevista(Postulacion $postulacion, User $entrevistador, Carbon $inicio, EstadoEntrevista $estado): Entrevista
    {
        $entrevista = Entrevista::firstOrNew(['postulacion_id' => $postulacion->id, 'entrevistador_id' => $entrevistador->id]);
        $entrevista->fill([
            'inicia_at' => $inicio,
            'duracion_min' => 45,
            'modalidad' => ModalidadEntrevista::Videollamada,
            'enlace' => 'https://meet.google.com/demo-'.$postulacion->id,
            'estado' => $estado,
        ]);
        $entrevista->empresa_id = $postulacion->empresa_id;
        $entrevista->save();

        return $entrevista;
    }

    /**
     * @param  list<int|float>  $puntajes  Técnico, comunicación, resolución de problemas, ajuste cultural.
     */
    private function evaluacion(Postulacion $postulacion, User $evaluador, array $puntajes, EstadoEvaluacion $estado): void
    {
        $criterios = array_map(
            fn (CriterioEvaluacion $c, int|float $p) => ['nombre' => $c->etiqueta(), 'puntaje' => $p],
            CriterioEvaluacion::cases(),
            $puntajes,
        );

        $evaluacion = Evaluacion::firstOrNew(['postulacion_id' => $postulacion->id, 'evaluador_id' => $evaluador->id]);
        $evaluacion->fill([
            'criterios' => $criterios,
            'puntaje' => Evaluacion::promediar($criterios),
            'estado' => $estado,
        ]);
        $evaluacion->empresa_id = $postulacion->empresa_id;
        $evaluacion->save();
    }

    /**
     * Una notificación de la campana con su propia hora (creadas en el mismo
     * segundo, su orden sería arbitrario). Se guarda con los datos del propio
     * aviso: el mismo texto que la aplicación enviaría.
     */
    private function aviso(User $usuario, Notification $aviso, int $minutosAtras): void
    {
        $usuario->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $aviso::class,
            'data' => method_exists($aviso, 'toArray') ? $aviso->toArray($usuario) : [],
            'created_at' => now()->subMinutes($minutosAtras),
        ]);
    }

    private function actividad(Empresa $empresa, ?User $actor, TipoActividad $tipo, string $descripcion, int $minutosAtras, ?Postulacion $sujeto = null): void
    {
        $actividad = Actividad::firstOrNew(['empresa_id' => $empresa->id, 'descripcion' => $descripcion]);
        $actividad->fill(['actor_id' => $actor?->id, 'tipo' => $tipo->value]);
        if ($sujeto !== null) {
            $actividad->sujeto()->associate($sujeto);
        }
        $actividad->empresa_id = $empresa->id;
        $actividad->created_at = now()->subMinutes($minutosAtras);
        $actividad->save();
    }

    /**
     * @param  list<Candidato>  $candidatos
     */
    private function sembrarCvs(array $candidatos, User $subidoPor): void
    {
        $disco = config('talentcloud.cvs.disco');

        foreach ($candidatos as $candidato) {
            $datos = CvsDeDemostracion::CVS[$candidato->email] ?? null;

            if ($datos === null || $candidato->cvs()->exists()) {
                continue;
            }

            $esDocx = str_ends_with($datos['archivo'], '.docx');
            $contenido = $esDocx ? DocumentosDePrueba::docx((string) $datos['texto']) : DocumentosDePrueba::pdf($datos['texto']);
            $ruta = 'candidatos/'.$candidato->id.'/'.Str::uuid().($esDocx ? '.docx' : '.pdf');

            Storage::disk($disco)->put($ruta, $contenido);

            $cv = Cv::create([
                'candidato_id' => $candidato->id,
                'subido_por' => $candidato->user_id ?? $subidoPor->id,
                'disco' => $disco,
                'ruta' => $ruta,
                'nombre_original' => $datos['archivo'],
                'mime' => $esDocx ? SubirCv::MIMES['docx'] : SubirCv::MIMES['pdf'],
                'tamano' => strlen($contenido),
            ]);

            ExtraerTextoCv::dispatchSync($cv->id);

            Postulacion::sinFiltroDeEmpresa()->where('candidato_id', $candidato->id)->update(['cv_id' => $cv->id]);
        }
    }

    /** Competencias que la IA extrajo de su CV (para que el aviso diga el número real). */
    private function extraidas(Candidato $candidato): int
    {
        return $candidato->todasLasCompetencias()->wherePivot('fuente', FuenteCompetencia::Extraccion->value)->count();
    }

    private function competencia(string $nombre): Competencia
    {
        return $this->competencias[$nombre] ??= Competencia::obtenerOCrear($nombre);
    }
}

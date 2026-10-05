<?php

namespace Database\Seeders;

use App\DTO\EvaluacionData;
use App\DTO\NuevaEntrevistaData;
use App\DTO\NuevoCandidatoData;
use App\DTO\RegistroPostulanteData;
use App\Enums\CriterioEvaluacion;
use App\Enums\EstadoEmpresa;
use App\Enums\EstadoEvaluacion;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\FuenteCompetencia;
use App\Enums\Modalidad;
use App\Enums\ModalidadEntrevista;
use App\Enums\PlanEmpresa;
use App\Enums\Rol;
use App\Enums\TipoRequisito;
use App\Events\RequisitosActualizados;
use App\Events\VacanteCreada;
use App\Ia\ServicioIa;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Invitacion;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Candidatos\RegistrarCandidato;
use App\Services\Cvs\SubirCv;
use App\Services\Empresas\CambiarEstadoEmpresa;
use App\Services\Entrevistas\CambiarEstadoEntrevista;
use App\Services\Entrevistas\ProgramarEntrevista;
use App\Services\Evaluaciones\GuardarEvaluacion;
use App\Services\Postulaciones\MoverPostulacion;
use App\Services\Postulaciones\PostularCandidato;
use App\Services\Postulaciones\PostularmeAVacante;
use App\Services\Registro\RegistrarPostulante;
use App\Services\Vacantes\CambiarEstadoVacante;
use App\Tenancy\TenantContext;
use Database\Seeders\Demo\CvsDeDemostracion;
use Database\Seeders\Demo\DocumentosDePrueba;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos de demostración con las personas y vacantes del prototipo
 * (TalentCloud App.dc.html). Todas las cuentas usan la contraseña "password".
 *
 * La demo no se escribe tabla a tabla: se REPRODUCE la historia de cada
 * postulación, en orden y en su fecha, con los mismos servicios que usan las
 * pantallas (registrar al candidato y su CV, postular, mover en el Kanban,
 * programar y completar entrevistas, evaluar, cerrar la vacante). Así la
 * trazabilidad es la que dejaría la aplicación en uso real: historial de
 * etapas, actividad con su autor y su postulación, avisos con su fecha,
 * estado comunicado al candidato y matching calculado al postular.
 *
 * Las fechas son relativas al día en que se siembra (el reloj se fija en cada
 * paso): lo pasado está completado y lo futuro, pendiente o confirmado.
 * Los trabajos corren en el momento (cola "sync") y el correo no sale
 * (mailer "array"). Si el servicio de IA no responde, se siembra con el
 * análisis básico y se avisa en consola.
 *
 * Es idempotente: si la historia ya se sembró, solo se actualizan las cuentas.
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /** @var array<string, Competencia> */
    private array $competencias = [];

    /** Día en que se siembra (la historia se cuenta hacia atrás desde aquí). */
    private Carbon $hoy;

    public function run(): void
    {
        $anterior = [
            'queue.default' => config('queue.default'),
            'mail.default' => config('mail.default'),
            'talentcloud.ia.habilitada' => config('talentcloud.ia.habilitada'),
        ];
        $relojAnterior = Carbon::getTestNow();

        config(['queue.default' => 'sync', 'mail.default' => 'array']);
        app('mail.manager')->forgetMailers();
        $this->prepararIa();

        try {
            app(TenantContext::class)->ejecutarGlobal(fn () => $this->sembrar());
        } finally {
            Carbon::setTestNow($relojAnterior);
            auth()->forgetUser();
            config($anterior);
            app('mail.manager')->forgetMailers();
        }
    }

    private function prepararIa(): void
    {
        $ia = app(ServicioIa::class);

        if ($ia->habilitado() && ! $ia->disponible()) {
            config(['talentcloud.ia.habilitada' => false]);
            $this->command->warn('El servicio de IA no responde: la demo se siembra con el análisis básico. '
                .'Cuando esté en marcha: php artisan talentcloud:reindexar-embeddings --sync y talentcloud:recalcular-matching.');
        }
    }

    private function sembrar(): void
    {
        $this->hoy = Carbon::today();
        $this->competencias = Competencia::all()->keyBy('nombre')->all();

        // — Empresas y su personal: dados de alta dos meses antes —
        $this->a(60, '09:00');
        $nimbus = $this->empresa('Nimbus Corp', PlanEmpresa::Enterprise, 'Tecnología', ['perfil' => true, 'vacante' => false, 'invitar' => true]);
        $vela = $this->empresa('Vela Studio', PlanEmpresa::Pro, 'Diseño', ['perfil' => true, 'vacante' => false, 'invitar' => false]);
        $orbita = $this->empresa('Orbita Labs', PlanEmpresa::Starter, 'Tecnología', ['perfil' => true, 'vacante' => false, 'invitar' => false]);

        $admin = $this->usuario('Administrador', 'admin@talentcloud.ai', Rol::Admin);
        $this->usuario('Nimbus Corp — Admin', 'admin@nimbus.com', Rol::Empresa, $nimbus);
        $marta = $this->usuario('Marta Solís', 'marta@nimbus.com', Rol::Reclutador, $nimbus);
        $diego = $this->usuario('Diego Ferro', 'diego@nimbus.com', Rol::Reclutador, $nimbus);
        $this->usuario('Vela Studio — Admin', 'admin@vela.studio', Rol::Empresa, $vela);
        $clara = $this->usuario('Clara Vidal', 'clara@vela.studio', Rol::Reclutador, $vela);
        $adminOrbita = $this->usuario('Orbita Labs — Admin', 'admin@orbita.dev', Rol::Empresa, $orbita);

        if (! Vacante::where('empresa_id', $nimbus->id)->where('titulo', 'Desarrollador Backend')->exists()) {
            $this->historia($admin, $marta, $diego, $clara, $adminOrbita, $orbita);
        }

        // "Ana López — Administradora — Invitado" de Mi empresa: invitada ayer.
        $this->a(1, '17:00');
        $invitacion = Invitacion::firstOrNew(['email' => 'ana.lopez@nimbus.com']);
        $invitacion->fill(['nombre' => 'Ana López', 'rol' => Rol::Empresa, 'token_hash' => hash('sha256', Str::random(40)), 'expira_at' => now()->addDays(7)]);
        $invitacion->empresa_id = $nimbus->id;
        $invitacion->save();
    }

    /**
     * La historia de la demo, día a día ("hace N días"). Cada línea es lo que
     * una persona hizo en la aplicación; los avisos, la actividad, el
     * historial y el matching los genera la propia aplicación.
     */
    private function historia(User $admin, User $marta, User $diego, User $clara, User $adminOrbita, Empresa $orbita): void
    {
        // Vela Studio abre DevOps; Marco crea su cuenta, sube su CV y postula.
        $this->a(40, '09:00', $clara);
        $devops = $this->vacante($clara, 'DevOps Engineer', 'Remoto', Modalidad::Remoto,
            'Infraestructura como código y despliegues continuos en la nube.', [
                ['Docker', TipoRequisito::Obligatorio, 4, null],
                ['Kubernetes', TipoRequisito::Obligatorio, 4, null],
                ['Terraform', TipoRequisito::Deseable, 3, null],
                ['AWS', TipoRequisito::Obligatorio, 4, null],
            ]);

        $this->a(39, '18:00');
        $marcoUsuario = $this->registrarPostulante('Marco Díaz', 'marco@correo.com', 'Desarrollador Backend');
        $this->a(39, '18:05', $marcoUsuario);
        app(SubirCv::class)->ejecutar($marcoUsuario->candidato, $this->archivoCv('marco@correo.com'), $marcoUsuario);

        $this->a(38, '10:00', $marcoUsuario);
        $marcoDevops = app(PostularmeAVacante::class)->ejecutar($marcoUsuario, $devops->id);

        $this->a(33, '10:00', $clara);
        $this->mover($marcoDevops, EtapaPostulacion::EnRevision, $clara);

        // Nimbus Corp abre Desarrollador Backend y registra a sus candidatos con su CV.
        $this->a(32, '09:00', $marta);
        $backend = $this->vacante($marta, 'Desarrollador Backend', 'Remoto', Modalidad::Remoto,
            'Buscamos una persona para diseñar y mantener APIs REST de alto tráfico con Java y Spring Boot.', [
                ['Java', TipoRequisito::Obligatorio, 5, 3],
                ['Spring Boot', TipoRequisito::Obligatorio, 4, 2],
                ['PostgreSQL', TipoRequisito::Obligatorio, 4, null],
                ['Docker', TipoRequisito::Deseable, 3, null],
                ['Kubernetes', TipoRequisito::Deseable, 2, null],
                ['Certificación AWS', TipoRequisito::Deseable, 2, null],
            ]);

        $this->a(31, '10:00', $marta);
        $elena = $this->registrarCandidato($marta, $backend, 'Elena Ruiz', 'elena.ruiz@correo.com', 'Desarrolladora Backend', ['Java', 'Spring Boot', 'PostgreSQL']);
        $this->a(30, '11:00', $marta);
        $sofia = $this->registrarCandidato($marta, $backend, 'Sofía Nieto', 'sofia.nieto@correo.com', 'Desarrolladora Backend', ['Java', 'Docker', 'Kubernetes']);

        $this->a(29, '09:00', $clara);
        $designer = $this->vacante($clara, 'Product Designer', 'Madrid', Modalidad::Presencial,
            'Diseño de producto de punta a punta: investigación, prototipado y sistema de diseño.', [
                ['Figma', TipoRequisito::Obligatorio, 5, 2],
                ['Research', TipoRequisito::Obligatorio, 4, null],
                ['Prototipado', TipoRequisito::Deseable, 3, null],
            ]);

        // El CV de Pablo es un PDF escaneado: no se puede leer y se le descarta en la revisión.
        $this->a(29, '12:00', $marta);
        $pablo = $this->registrarCandidato($marta, $backend, 'Pablo Vega', 'pablo.vega@correo.com', 'QA Engineer', ['Selenium']);

        $this->a(28, '10:00', $marta);
        $this->mover($elena, EtapaPostulacion::EnRevision, $marta);
        $this->a(27, '10:00', $marta);
        $this->mover($pablo, EtapaPostulacion::Descartado, $marta);
        $this->a(27, '15:00', $clara);
        $ivan = $this->registrarCandidato($clara, $designer, 'Iván Torres', 'ivan.torres@correo.com', 'Diseñador de Producto', ['Figma', 'Research']);
        $this->a(27, '16:00', $marta);
        $this->mover($sofia, EtapaPostulacion::EnRevision, $marta);

        // DevOps cierra con Marco aún en revisión: se le avisa del cierre.
        $this->a(25, '09:00', $clara);
        app(CambiarEstadoVacante::class)->ejecutar($devops->fresh(), EstadoVacante::Cerrada);

        $this->a(24, '09:00', $diego);
        $dataAnalyst = $this->vacante($diego, 'Data Analyst', 'Remoto', Modalidad::Remoto,
            'Análisis de datos de negocio y construcción de tableros para las áreas comerciales.', [
                ['SQL', TipoRequisito::Obligatorio, 5, 2],
                ['Python', TipoRequisito::Obligatorio, 4, null],
                ['Power BI', TipoRequisito::Deseable, 3, null],
            ]);
        $this->a(23, '10:00', $diego);
        $lucia = $this->registrarCandidato($diego, $dataAnalyst, 'Lucía Prat', 'lucia.prat@correo.com', 'Analista de Datos', ['SQL', 'Python']);
        $this->a(21, '10:00', $diego);
        $this->mover($lucia, EtapaPostulacion::EnRevision, $diego);

        // Orbita Labs deja un borrador sin publicar; después la plataforma la suspende.
        $this->a(20, '09:00', $adminOrbita);
        $this->vacante($adminOrbita, 'QA Engineer', 'Bogotá', Modalidad::Hibrido,
            'Automatización de pruebas web y de APIs.', [
                ['Selenium', TipoRequisito::Obligatorio, 4, null],
                ['Cypress', TipoRequisito::Deseable, 3, null],
                ['Testing de APIs', TipoRequisito::Obligatorio, 4, null],
            ], publicar: false);
        $this->a(20, '11:00', $clara);
        $this->mover($ivan, EtapaPostulacion::EnRevision, $clara);

        // Lucía: la primera entrevista se cancela y se reprograma con Diego; la aprueba.
        $this->a(18, '10:00', $diego);
        $canceladaLucia = $this->programar($diego, $lucia, $marta, $this->dia(-15, '11:00'));
        $this->a(16, '09:00', $marta);
        app(CambiarEstadoEntrevista::class)->cancelar($canceladaLucia);
        $this->a(16, '09:30', $diego);
        $entrevistaLucia = $this->programar($diego, $lucia, $diego, $this->dia(-13, '09:30'));
        $this->a(15, '18:00', $diego);
        app(CambiarEstadoEntrevista::class)->confirmar($entrevistaLucia);

        $this->a(15, '10:00', $admin);
        app(CambiarEstadoEmpresa::class)->ejecutar($orbita->fresh());

        // Sofía: entrevista con Marta, evaluación aprobada y oferta.
        $this->a(14, '10:00', $marta);
        $entrevistaSofia = $this->programar($marta, $sofia, $marta, $this->dia(-10, '11:00'));
        app(CambiarEstadoEntrevista::class)->confirmar($entrevistaSofia);

        $this->a(13, '10:30', $diego);
        app(CambiarEstadoEntrevista::class)->completar($entrevistaLucia);
        $this->a(13, '12:00', $diego);
        $this->evaluar($diego, $lucia, [8, 8, 7.6, 8], 'Domina SQL y explica bien sus análisis; reforzar Power BI.');

        // Marco postula por su cuenta a Desarrollador Backend.
        $this->a(12, '10:00', $marcoUsuario);
        app(PostularmeAVacante::class)->ejecutar($marcoUsuario, $backend->id);
        // Al revisar su CV, Marta le anota las competencias que le interesan a Nimbus Corp.
        $this->a(11, '09:30', $marta);
        $this->competenciasManuales($marcoUsuario->candidato()->firstOrFail(), ['Java', 'MySQL'], $marta->empresa);

        $this->a(10, '09:00', $diego);
        app(CambiarEstadoVacante::class)->ejecutar($dataAnalyst->fresh(), EstadoVacante::EnRevision);
        $this->a(10, '11:50', $marta);
        app(CambiarEstadoEntrevista::class)->completar($entrevistaSofia);

        // Elena: primera entrevista con Marta.
        $this->a(9, '10:00', $marta);
        $entrevistaElena = $this->programar($marta, $elena, $marta, $this->dia(-6, '10:00'));
        $this->a(9, '12:00', $marta);
        $this->evaluar($marta, $sofia, [9, 8.5, 9, 9], 'Experiencia sólida con Kubernetes y buena comunicación con el equipo.');
        $this->a(8, '09:00', $marta);
        app(CambiarEstadoEntrevista::class)->confirmar($entrevistaElena);
        $this->a(6, '10:50', $marta);
        app(CambiarEstadoEntrevista::class)->completar($entrevistaElena);
        $this->a(5, '10:00', $marta);
        $this->evaluar($marta, $elena, [9, 8, 9, 8.4], 'Muy buen diseño de APIs; pasa a la entrevista técnica con Diego.');

        // Iván: entrevista con Clara dentro de tres días, ya confirmada.
        $this->a(5, '12:00', $clara);
        $entrevistaIvan = $this->programar($clara, $ivan, $clara, $this->dia(3, '10:00'));
        $this->a(4, '09:00', $clara);
        app(CambiarEstadoEntrevista::class)->confirmar($entrevistaIvan);

        // Ofertas a Lucía y a Sofía.
        $this->a(4, '10:00', $diego);
        $this->mover($lucia, EtapaPostulacion::Oferta, $diego);
        $this->a(3, '10:00', $marta);
        $this->mover($sofia, EtapaPostulacion::Oferta, $marta);

        // Elena: segunda entrevista con Diego, por confirmar.
        $this->a(1, '16:00', $diego);
        $this->programar($diego, $elena, $diego, $this->dia(2, '15:00'));
    }

    /**
     * Fija el reloj en "hace $haceDias días, a la $hora" y actúa como $quien
     * (con su contexto de empresa; sin nadie, como la plataforma).
     */
    private function a(int $haceDias, string $hora, ?User $quien = null): void
    {
        Carbon::setTestNow($this->dia(-$haceDias, $hora));

        if ($quien === null) {
            auth()->forgetUser();
            app(TenantContext::class)->establecerGlobal();

            return;
        }

        auth()->setUser($quien);
        app(TenantContext::class)->establecerParaUsuario($quien);
    }

    /** Día relativo al de la siembra ($dias negativo = pasado) a una hora. */
    private function dia(int $dias, string $hora): Carbon
    {
        return $this->hoy->copy()->addDays($dias)->setTimeFromTimeString($hora);
    }

    /**
     * @param  array<string, bool>  $onboarding  estado inicial; la aplicación lo avanza al crear vacantes
     */
    private function empresa(string $nombre, PlanEmpresa $plan, string $sector, array $onboarding): Empresa
    {
        $empresa = Empresa::firstOrNew(['nombre' => $nombre]);

        if (! $empresa->exists) {
            $empresa->fill(['estado' => EstadoEmpresa::Activa, 'onboarding' => $onboarding]);
        }

        $empresa->fill(['plan' => $plan, 'sector' => $sector])->save();

        return $empresa;
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
     * Crea la vacante como borrador con sus requisitos (VacanteCreada y
     * RequisitosActualizados, como en la pantalla) y la publica media hora después.
     *
     * @param  list<array{0: string, 1: TipoRequisito, 2: int, 3: int|null}>  $requisitos
     */
    private function vacante(User $autor, string $titulo, string $ubicacion, Modalidad $modalidad, string $descripcion, array $requisitos, bool $publicar = true): Vacante
    {
        $vacante = new Vacante([
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'ubicacion' => $ubicacion,
            'modalidad' => $modalidad,
            'estado' => EstadoVacante::Borrador,
        ]);
        $vacante->empresa_id = (int) $autor->empresa_id;
        $vacante->creada_por = $autor->id;
        $vacante->save();

        foreach ($requisitos as $orden => [$competencia, $tipo, $peso, $anios]) {
            $requisito = new Requisito(['vacante_id' => $vacante->id, 'competencia_id' => $this->competencia($competencia)->id]);
            $requisito->fill(['tipo' => $tipo, 'peso' => $peso, 'anios_minimos' => $anios, 'orden' => $orden]);
            $requisito->empresa_id = $vacante->empresa_id;
            $requisito->save();
        }

        VacanteCreada::dispatch($vacante, $autor);
        RequisitosActualizados::dispatch($vacante);

        if ($publicar) {
            Carbon::setTestNow(now()->addMinutes(30));
            $vacante = app(CambiarEstadoVacante::class)->ejecutar($vacante, EstadoVacante::Abierta);
        }

        return $vacante;
    }

    /**
     * "+ Nuevo candidato" con CV y vacante: registro, CV y postulación en la
     * misma transacción (la extracción y el análisis corren al confirmarse).
     *
     * @param  list<string>  $competencias  competencias manuales que anota la empresa
     */
    private function registrarCandidato(User $reclutador, Vacante $vacante, string $nombre, string $email, string $titular, array $competencias): Postulacion
    {
        $empresa = $reclutador->empresa;

        $postulacion = DB::transaction(function () use ($reclutador, $empresa, $vacante, $nombre, $email, $titular): Postulacion {
            $candidato = app(RegistrarCandidato::class)->ejecutar($empresa, new NuevoCandidatoData($nombre, $email, null, $titular, true));
            app(SubirCv::class)->ejecutar($candidato, $this->archivoCv($email), $reclutador);

            return app(PostularCandidato::class)->ejecutar($vacante, $candidato);
        });

        $this->competenciasManuales($postulacion->candidato()->firstOrFail(), $competencias, $empresa);

        return $postulacion;
    }

    /** Registro de postulante (con su titular, que completa en "Mi perfil"). */
    private function registrarPostulante(string $nombre, string $email, string $titular): User
    {
        $usuario = app(RegistrarPostulante::class)->ejecutar(new RegistroPostulanteData($nombre, $email, self::PASSWORD, true));
        $usuario->forceFill(['email_verified_at' => now()])->save();

        $usuario->candidato()->firstOrFail()->update(['titular' => $titular]);

        return $usuario->fresh() ?? $usuario;
    }

    /**
     * @param  list<string>  $competencias
     */
    private function competenciasManuales(Candidato $candidato, array $competencias, ?Empresa $empresa): void
    {
        $candidato->todasLasCompetencias()
            ->withPivotValue('fuente', FuenteCompetencia::Manual->value)
            ->withPivotValue('empresa_id', $empresa?->id)
            ->sync(array_map(fn (string $c) => $this->competencia($c)->id, $competencias));
    }

    /** El CV de demostración como un archivo subido (PDF o Word reales). */
    private function archivoCv(string $email): UploadedFile
    {
        $datos = CvsDeDemostracion::CVS[$email];
        $esDocx = str_ends_with($datos['archivo'], '.docx');
        $contenido = $esDocx ? DocumentosDePrueba::docx((string) $datos['texto']) : DocumentosDePrueba::pdf($datos['texto']);

        return UploadedFile::fake()->createWithContent($datos['archivo'], $contenido);
    }

    /** Mueve la postulación al final de la columna de destino, como al arrastrarla. */
    private function mover(Postulacion $postulacion, EtapaPostulacion $destino, User $quien): void
    {
        $postulacion = Postulacion::findOrFail($postulacion->id);
        $alFinal = Postulacion::where('vacante_id', $postulacion->vacante_id)->where('etapa', $destino)->count();

        app(MoverPostulacion::class)->ejecutar($postulacion, $destino, $alFinal, $quien);
    }

    /** "+ Programar entrevista" con la casilla «Mover a Entrevista» marcada (por defecto). */
    private function programar(User $quien, Postulacion $postulacion, User $entrevistador, Carbon $inicio): Entrevista
    {
        return app(ProgramarEntrevista::class)->ejecutar(new NuevaEntrevistaData(
            postulacionId: $postulacion->id,
            entrevistadorId: $entrevistador->id,
            iniciaAt: $inicio->toImmutable(),
            duracionMin: 45,
            modalidad: ModalidadEntrevista::Videollamada,
            enlace: 'https://meet.google.com/demo-'.$postulacion->id,
            notas: null,
            moverAEntrevista: true,
        ), $quien);
    }

    /**
     * @param  list<int|float>  $puntajes  Técnico, comunicación, resolución de problemas, ajuste cultural.
     */
    private function evaluar(User $evaluador, Postulacion $postulacion, array $puntajes, string $comentario): void
    {
        $porCriterio = array_combine(
            array_map(fn (CriterioEvaluacion $c) => $c->value, CriterioEvaluacion::cases()),
            array_map(fn (int|float $p) => (float) $p, $puntajes),
        );

        app(GuardarEvaluacion::class)->registrar($postulacion->id, $evaluador, new EvaluacionData($porCriterio, $comentario, EstadoEvaluacion::Aprobado));
    }

    private function competencia(string $nombre): Competencia
    {
        return $this->competencias[$nombre] ??= Competencia::obtenerOCrear($nombre);
    }
}

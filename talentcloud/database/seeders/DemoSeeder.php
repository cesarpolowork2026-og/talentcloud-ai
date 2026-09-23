<?php

namespace Database\Seeders;

use App\Enums\EstadoEmpresa;
use App\Enums\EstadoEntrevista;
use App\Enums\EstadoEvaluacion;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\FuenteCompetencia;
use App\Enums\Modalidad;
use App\Enums\ModalidadEntrevista;
use App\Enums\PlanEmpresa;
use App\Enums\Rol;
use App\Enums\TipoRequisito;
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
use App\Notifications\AvisoGeneral;
use App\Services\Cvs\SubirCv;
use App\Tenancy\TenantContext;
use Database\Seeders\Demo\CvsDeDemostracion;
use Database\Seeders\Demo\DocumentosDePrueba;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Datos de demostración tomados del prototipo (TalentCloud App.dc.html), para
 * comparar cada pantalla con el diseño. Todas las cuentas usan la contraseña
 * "password".
 *
 * Ajuste respecto al prototipo: su vista mezcla empresas. Aquí el equipo de
 * Nimbus Corp (Marta Solís, Diego Ferro) solo trabaja con vacantes de Nimbus,
 * así que las entrevistas y evaluaciones de Iván Torres y Pablo Vega son de
 * "Desarrollador Backend" (donde también están en el Kanban del prototipo).
 *
 * Los CV y el detalle del matching se siembran cuando existan sus módulos
 * (días 9 y 11 del plan).
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
        $this->usuario('Clara Vidal', 'clara@vela.studio', Rol::Reclutador, $vela);
        $this->usuario('Orbita Labs — Admin', 'admin@orbita.dev', Rol::Empresa, $orbita);
        $marcoUsuario = $this->usuario('Marco Díaz', 'marco@correo.com', Rol::Postulante);

        // "Ana López — Administradora — Invitado" de Mi empresa.
        $invitacion = Invitacion::firstOrNew(['email' => 'ana.lopez@nimbus.com']);
        $invitacion->fill(['nombre' => 'Ana López', 'rol' => Rol::Empresa, 'token_hash' => hash('sha256', Str::random(40)), 'expira_at' => now()->addDays(7)]);
        $invitacion->empresa_id = $nimbus->id;
        $invitacion->save();

        // — Vacantes (pantalla "Vacantes") —
        $backend = $this->vacante($nimbus, 'Desarrollador Backend', 'Remoto', Modalidad::Remoto, EstadoVacante::Abierta, '2026-09-02', $marta,
            'Buscamos una persona para diseñar y mantener APIs REST de alto tráfico con Java y Spring Boot.', [
                ['Java', TipoRequisito::Obligatorio, 5, 3],
                ['Spring Boot', TipoRequisito::Obligatorio, 4, 2],
                ['PostgreSQL', TipoRequisito::Obligatorio, 4, null],
                ['Docker', TipoRequisito::Deseable, 3, null],
                ['Kubernetes', TipoRequisito::Deseable, 2, null],
                ['Certificación AWS', TipoRequisito::Deseable, 2, null],
            ]);
        $designer = $this->vacante($vela, 'Product Designer', 'Madrid', Modalidad::Presencial, EstadoVacante::Abierta, '2026-09-05', null,
            'Diseño de producto de punta a punta: investigación, prototipado y sistema de diseño.', [
                ['Figma', TipoRequisito::Obligatorio, 5, 2],
                ['Research', TipoRequisito::Obligatorio, 4, null],
                ['Prototipado', TipoRequisito::Deseable, 3, null],
            ]);
        $dataAnalyst = $this->vacante($nimbus, 'Data Analyst', 'Remoto', Modalidad::Remoto, EstadoVacante::EnRevision, '2026-09-10', $diego,
            'Análisis de datos de negocio y construcción de tableros para las áreas comerciales.', [
                ['SQL', TipoRequisito::Obligatorio, 5, 2],
                ['Python', TipoRequisito::Obligatorio, 4, null],
                ['Power BI', TipoRequisito::Deseable, 3, null],
            ]);
        $qa = $this->vacante($orbita, 'QA Engineer', 'Bogotá', Modalidad::Hibrido, EstadoVacante::Borrador, '2026-09-14', null,
            'Automatización de pruebas web y de APIs.', [
                ['Selenium', TipoRequisito::Obligatorio, 4, null],
                ['Cypress', TipoRequisito::Deseable, 3, null],
                ['Testing de APIs', TipoRequisito::Obligatorio, 4, null],
            ]);
        $devops = $this->vacante($vela, 'DevOps Engineer', 'Remoto', Modalidad::Remoto, EstadoVacante::Cerrada, '2026-09-01', null,
            'Infraestructura como código y despliegues continuos en la nube.', [
                ['Docker', TipoRequisito::Obligatorio, 4, null],
                ['Kubernetes', TipoRequisito::Obligatorio, 4, null],
                ['Terraform', TipoRequisito::Deseable, 3, null],
                ['AWS', TipoRequisito::Obligatorio, 4, null],
            ]);

        // — Candidatos (pantalla "Candidatos") con sus competencias —
        $elena = $this->candidato('Elena Ruiz', 'elena.ruiz@correo.com', 'Desarrolladora Backend', $nimbus, ['Java', 'Spring Boot', 'PostgreSQL']);
        $sofia = $this->candidato('Sofía Nieto', 'sofia.nieto@correo.com', 'Desarrolladora Backend', $nimbus, ['Java', 'Docker', 'Kubernetes']);
        $marco = $this->candidato('Marco Díaz', 'marco@correo.com', 'Desarrollador Backend', null, ['Java', 'MySQL'], $marcoUsuario);
        $ivan = $this->candidato('Iván Torres', 'ivan.torres@correo.com', 'Diseñador de Producto', $vela, ['Figma', 'Research']);
        $lucia = $this->candidato('Lucía Prat', 'lucia.prat@correo.com', 'Analista de Datos', $nimbus, ['SQL', 'Python']);
        $pablo = $this->candidato('Pablo Vega', 'pablo.vega@correo.com', 'QA Engineer', $orbita, ['Selenium']);

        // — Postulaciones: Kanban "Desarrollador Backend" del prototipo —
        $pElena = $this->postulacion($backend, $elena, EtapaPostulacion::Postulado, 0, 88);
        $pMarco = $this->postulacion($backend, $marco, EtapaPostulacion::Postulado, 1, 64);
        $pSofia = $this->postulacion($backend, $sofia, EtapaPostulacion::EnRevision, 0, 91);
        $pIvan = $this->postulacion($backend, $ivan, EtapaPostulacion::Entrevista, 0, 79);
        $this->postulacion($backend, $lucia, EtapaPostulacion::Oferta, 0, 95);
        $pPablo = $this->postulacion($backend, $pablo, EtapaPostulacion::Descartado, 0, 42);

        // Postulaciones a la vacante de su perfil (lista de Candidatos y "Mis postulaciones").
        $this->postulacion($designer, $ivan, EtapaPostulacion::EnRevision, 0, 79);
        $pLuciaData = $this->postulacion($dataAnalyst, $lucia, EtapaPostulacion::Oferta, 0, 95);
        $this->postulacion($qa, $pablo, EtapaPostulacion::Postulado, 0, 42);
        $this->postulacion($devops, $marco, EtapaPostulacion::Descartado, 0, 58, '2026-08-20');

        // — Entrevistas (pantalla "Entrevistas") —
        $this->entrevista($pElena, $marta, '2026-09-23 10:00', EstadoEntrevista::Confirmada);
        $this->entrevista($pSofia, $marta, '2026-09-23 15:00', EstadoEntrevista::Confirmada);
        $this->entrevista($pIvan, $diego, '2026-09-24 09:30', EstadoEntrevista::Pendiente);
        $this->entrevista($pLuciaData, $marta, '2026-09-25 11:00', EstadoEntrevista::Cancelada);

        // — Evaluaciones (pantalla "Evaluaciones") —
        $this->evaluacion($pElena, $marta, [9, 8, 9, 8.4], EstadoEvaluacion::Aprobado);
        $this->evaluacion($pMarco, $marta, [5, 6, 5, 5.6], EstadoEvaluacion::EnRevision);
        $this->evaluacion($pIvan, $diego, [8, 8, 7.6, 8], EstadoEvaluacion::Aprobado);
        $this->evaluacion($pPablo, $diego, [3, 3.4, 3, 3], EstadoEvaluacion::Pendiente);

        // — CVs (archivos reales en el disco privado, procesados al sembrar) —
        $this->sembrarCvs([$elena, $sofia, $marco, $ivan, $lucia, $pablo], $marta);

        // — Actividad reciente y notificaciones —
        $this->actividad($nimbus, null, 'cv.analizados', 'La IA analizó 6 CVs nuevos para Desarrollador Backend.', 40);
        $this->actividad($nimbus, $marta, 'postulacion.etapa', 'Sofía Nieto avanzó a la etapa de entrevista.', 30);
        $this->actividad($orbita, null, 'vacante.creada', 'Orbita Labs publicó una nueva vacante en borrador.', 20);
        $this->actividad($nimbus, $marta, 'evaluacion.aprobada', 'Marta Solís aprobó la evaluación de Elena Ruiz.', 10);

        if ($marta->notifications()->doesntExist()) {
            $marta->notify(new AvisoGeneral('Orbita Labs actualizó su plan a Starter.'));
            $marta->notify(new AvisoGeneral('Entrevista de Iván Torres pendiente de confirmar.'));
            $marta->notify(new AvisoGeneral('Nuevo CV analizado para Desarrollador Backend (Elena Ruiz).'));
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
    private function vacante(Empresa $empresa, string $titulo, string $ubicacion, Modalidad $modalidad, EstadoVacante $estado, string $creada, ?User $creador, string $descripcion, array $requisitos): Vacante
    {
        $fecha = Carbon::parse($creada.' 09:00');

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
     * @param  list<string>  $competencias
     */
    private function candidato(string $nombre, string $email, string $titular, ?Empresa $origen, array $competencias, ?User $usuario = null): Candidato
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

        $candidato->competencias()->syncWithPivotValues(
            array_map(fn (string $c) => $this->competencia($c)->id, $competencias),
            ['fuente' => FuenteCompetencia::Manual->value],
        );

        return $candidato;
    }

    private function postulacion(Vacante $vacante, Candidato $candidato, EtapaPostulacion $etapa, int $orden, int $match, ?string $fecha = null): Postulacion
    {
        $postulacion = Postulacion::firstOrNew(['vacante_id' => $vacante->id, 'candidato_id' => $candidato->id]);
        $postulacion->fill([
            'etapa' => $etapa,
            'orden' => $orden,
            'etapa_cambiada_at' => now(),
            'match_score' => $match,
        ]);
        $postulacion->empresa_id = $vacante->empresa_id;
        $postulacion->created_at = $fecha ? Carbon::parse($fecha.' 12:00') : $vacante->created_at->copy()->addDay();
        $postulacion->save();

        return $postulacion;
    }

    private function entrevista(Postulacion $postulacion, User $entrevistador, string $inicio, EstadoEntrevista $estado): void
    {
        $entrevista = Entrevista::firstOrNew(['postulacion_id' => $postulacion->id, 'entrevistador_id' => $entrevistador->id]);
        $entrevista->fill([
            'inicia_at' => Carbon::parse($inicio),
            'duracion_min' => 45,
            'modalidad' => ModalidadEntrevista::Videollamada,
            'enlace' => 'https://meet.google.com/demo-'.$postulacion->id,
            'estado' => $estado,
        ]);
        $entrevista->empresa_id = $postulacion->empresa_id;
        $entrevista->save();
    }

    /**
     * @param  list<int|float>  $puntajes  Técnico, comunicación, resolución de problemas, ajuste cultural.
     */
    private function evaluacion(Postulacion $postulacion, User $evaluador, array $puntajes, EstadoEvaluacion $estado): void
    {
        $nombres = ['Conocimiento técnico', 'Comunicación', 'Resolución de problemas', 'Ajuste cultural'];
        $criterios = array_map(fn (string $n, int|float $p) => ['nombre' => $n, 'puntaje' => $p], $nombres, $puntajes);

        $evaluacion = Evaluacion::firstOrNew(['postulacion_id' => $postulacion->id, 'evaluador_id' => $evaluador->id]);
        $evaluacion->fill([
            'criterios' => $criterios,
            'puntaje' => Evaluacion::promediar($criterios),
            'estado' => $estado,
        ]);
        $evaluacion->empresa_id = $postulacion->empresa_id;
        $evaluacion->save();
    }

    private function actividad(Empresa $empresa, ?User $actor, string $tipo, string $descripcion, int $minutosAtras): void
    {
        $actividad = Actividad::firstOrNew(['empresa_id' => $empresa->id, 'descripcion' => $descripcion]);
        $actividad->fill(['actor_id' => $actor?->id, 'tipo' => $tipo]);
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

    private function competencia(string $nombre): Competencia
    {
        return $this->competencias[$nombre] ??= Competencia::obtenerOCrear($nombre);
    }
}

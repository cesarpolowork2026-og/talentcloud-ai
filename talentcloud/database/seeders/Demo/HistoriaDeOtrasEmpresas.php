<?php

namespace Database\Seeders\Demo;

use App\DTO\RegistroEmpresaData;
use App\Enums\EstadoEvaluacion;
use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Enums\Modalidad;
use App\Enums\PlanEmpresa;
use App\Enums\Rol;
use App\Enums\TipoRequisito;
use App\Models\Empresa;
use App\Models\Entrevista;
use App\Models\Postulacion;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Cvs\SubirCv;
use App\Services\Empresas\SolicitarCambioDePlan;
use App\Services\Entrevistas\CambiarEstadoEntrevista;
use App\Services\Postulaciones\PostularmeAVacante;
use App\Services\Postulaciones\RetirarPostulacion;
use App\Services\Registro\RegistrarEmpresa;
use App\Services\Vacantes\CambiarEstadoVacante;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Historia de las empresas que no son de tecnología. Sus pasos se agendan con su fecha y DemoSeeder los ejecuta
 * intercalados con los suyos, en orden cronológico. Las horas evitan las de la
 * historia de Nimbus Corp, Vela Studio y Orbita Labs, porque el reloj nunca
 * retrocede.
 */
trait HistoriaDeOtrasEmpresas
{
    /** @var list<array{momento: Carbon, quien: ?string, paso: callable(): mixed}> */
    private array $agenda = [];

    /** @var array<string, User> */
    private array $gente = [];

    /** @var array<string, Empresa> */
    private array $empresas = [];

    /** @var array<string, Vacante> */
    private array $vacantes = [];

    /** @var array<string, Postulacion> */
    private array $postulaciones = [];

    /** @var array<string, Entrevista> */
    private array $entrevistas = [];

    /** Agenda un paso "hace $haceDias días, a la $hora", hecho por $quien (clave de $gente; null = la plataforma o un visitante). */
    private function en(int $haceDias, string $hora, ?string $quien, callable $paso): void
    {
        $this->agenda[] = ['momento' => $this->dia(-$haceDias, $hora), 'quien' => $quien, 'paso' => $paso];
    }

    /** Ejecuta, en orden, los pasos agendados antes de $limite (todos, sin límite). */
    private function ejecutarAgendaHasta(?Carbon $limite = null): void
    {
        // usort es estable: dos pasos a la misma hora se ejecutan en el orden en que se agendaron.
        usort($this->agenda, fn (array $a, array $b) => $a['momento'] <=> $b['momento']);

        while ($this->agenda !== [] && ($limite === null || $this->agenda[0]['momento']->lessThan($limite))) {
            $siguiente = array_shift($this->agenda);

            if ($siguiente['momento']->lessThan(now())) {
                throw new LogicException('Un paso de la demo cae dentro de otro ('.$siguiente['momento']->toDateTimeString().'): el reloj retrocedería.');
            }

            $this->fijarReloj($siguiente['momento'], $siguiente['quien'] === null ? null : $this->gente[$siguiente['quien']]);
            ($siguiente['paso'])();
        }
    }

    private function agendarOtrasEmpresas(): void
    {
        $this->agendarRutaSur();
        $this->agendarBrio();
        $this->agendarNuna();
        $this->agendarAlturaCafe();
    }

    /** Ruta Sur Logística (Starter): contrata un jefe de almacén y cierra la vacante; luego busca un analista. */
    private function agendarRutaSur(): void
    {
        $this->en(57, '08:30', null, function () {
            $this->empresas['rutasur'] = $this->empresa('Ruta Sur Logística', PlanEmpresa::Starter, 'Logística', ['perfil' => true, 'vacante' => false, 'invitar' => true]);
            $this->gente['adminRutaSur'] = $this->usuario('Ruta Sur Logística — Admin', 'admin@rutasur.pe', Rol::Empresa, $this->empresas['rutasur']);
            $this->gente['jorge'] = $this->usuario('Jorge Huamán', 'jorge@rutasur.pe', Rol::Reclutador, $this->empresas['rutasur']);
        });

        $this->en(55, '08:30', 'jorge', fn () => $this->vacantes['almacen'] = $this->vacante($this->gente['jorge'], 'Jefe de Almacén', 'Lurín', Modalidad::Presencial,
            'Liderar el centro de distribución de Lurín: recepción, almacenamiento y despacho en tres turnos.', [
                ['Gestión de almacenes', TipoRequisito::Obligatorio, 5, 3],
                ['Gestión de inventarios', TipoRequisito::Obligatorio, 4, null],
                ['Liderazgo de equipos', TipoRequisito::Obligatorio, 4, null],
                ['Excel avanzado', TipoRequisito::Deseable, 2, null],
            ]));
        $this->en(54, '11:15', 'jorge', fn () => $this->postulaciones['manuel'] = $this->registrarCandidato($this->gente['jorge'], $this->vacantes['almacen'], 'Manuel Quispe', 'manuel.quispe@correo.com', 'Jefe de Almacén', ['Gestión de almacenes']));
        $this->en(54, '15:30', 'jorge', fn () => $this->postulaciones['rosa'] = $this->registrarCandidato($this->gente['jorge'], $this->vacantes['almacen'], 'Rosa Condori', 'rosa.condori@correo.com', 'Supervisora de Almacén', []));
        $this->en(53, '17:30', 'jorge', fn () => $this->postulaciones['wilmer'] = $this->registrarCandidato($this->gente['jorge'], $this->vacantes['almacen'], 'Wilmer Ccori', 'wilmer.ccori@correo.com', 'Operario de Almacén', []));
        $this->en(52, '11:15', 'jorge', fn () => $this->moverVarias(['manuel' => EtapaPostulacion::EnRevision, 'rosa' => EtapaPostulacion::EnRevision], 'jorge'));
        $this->en(51, '10:15', 'jorge', fn () => $this->mover($this->postulaciones['wilmer'], EtapaPostulacion::Descartado, $this->gente['jorge']));
        $this->en(50, '11:15', 'jorge', fn () => $this->programarYConfirmar('manuel', 'jorge', -47, '10:00'));
        $this->en(50, '11:30', 'jorge', fn () => $this->programarYConfirmar('rosa', 'jorge', -47, '15:00'));
        $this->en(47, '11:00', 'jorge', fn () => $this->completar('manuel'));
        $this->en(47, '16:00', 'jorge', fn () => $this->completar('rosa'));
        $this->en(46, '17:30', 'jorge', function () {
            $this->evaluar($this->gente['jorge'], $this->postulaciones['manuel'], [9, 8.5, 8.8, 9], 'Nueve años en centros de distribución y manejo de WMS; muy claro al explicar sus indicadores.');
            $this->evaluar($this->gente['jorge'], $this->postulaciones['rosa'], [6.5, 7, 6, 7.5], 'Buena actitud, pero no ha liderado almacenes de este tamaño ni trabajado con WMS.', EstadoEvaluacion::Rechazado);
            $this->mover($this->postulaciones['rosa'], EtapaPostulacion::Descartado, $this->gente['jorge']);
        });
        $this->en(44, '11:15', 'jorge', fn () => $this->mover($this->postulaciones['manuel'], EtapaPostulacion::Oferta, $this->gente['jorge']));
        $this->en(41, '16:45', 'jorge', fn () => $this->mover($this->postulaciones['manuel'], EtapaPostulacion::Contratado, $this->gente['jorge']));
        $this->en(40, '16:45', 'jorge', fn () => app(CambiarEstadoVacante::class)->ejecutar($this->vacantes['almacen']->fresh(), EstadoVacante::Cerrada));

        // Analista de Logística: Karen postula por su cuenta y llega a la oferta.
        $this->en(30, '14:30', 'jorge', fn () => $this->vacantes['logistica'] = $this->vacante($this->gente['jorge'], 'Analista de Logística', 'Callao', Modalidad::Hibrido,
            'Planificar la distribución de última milla y el seguimiento de importaciones con indicadores en Power BI.', [
                ['Distribución', TipoRequisito::Obligatorio, 4, null],
                ['Excel avanzado', TipoRequisito::Obligatorio, 4, null],
                ['Power BI', TipoRequisito::Deseable, 3, null],
                ['Comercio exterior', TipoRequisito::Deseable, 2, null],
                ['SQL', TipoRequisito::Deseable, 2, null],
            ]));
        $this->postulantePostula(29, 'karen', 'Karen Espinoza', 'karen.espinoza@correo.com', 'Analista de Logística', 'logistica');
        $this->en(28, '16:30', 'jorge', fn () => $this->postulaciones['lorena'] = $this->registrarCandidato($this->gente['jorge'], $this->vacantes['logistica'], 'Lorena Paz', 'lorena.paz@correo.com', 'Analista de Operaciones', []));
        $this->en(27, '11:30', 'jorge', fn () => $this->postulaciones['oscar'] = $this->registrarCandidato($this->gente['jorge'], $this->vacantes['logistica'], 'Óscar Vilca', 'oscar.vilca@correo.com', 'Coordinador de Transporte', ['Distribución']));
        $this->en(26, '11:15', 'jorge', fn () => $this->postulaciones['bruno'] = $this->registrarCandidato($this->gente['jorge'], $this->vacantes['logistica'], 'Bruno Medina', 'bruno.medina@correo.com', 'Asistente de Compras', []));
        $this->en(24, '15:15', 'jorge', fn () => $this->moverVarias(['karen' => EtapaPostulacion::EnRevision, 'lorena' => EtapaPostulacion::EnRevision, 'bruno' => EtapaPostulacion::Descartado], 'jorge'));
        $this->en(22, '11:15', 'jorge', fn () => $this->programarYConfirmar('karen', 'jorge', -19, '11:00'));
        $this->en(19, '11:50', 'jorge', fn () => $this->completar('karen'));
        $this->en(19, '15:00', 'jorge', fn () => $this->evaluar($this->gente['jorge'], $this->postulaciones['karen'], [8.5, 9, 8.4, 8.8], 'Planifica rutas con datos y conoce el proceso de importación de punta a punta.'));
        $this->en(17, '11:15', 'jorge', fn () => $this->mover($this->postulaciones['oscar'], EtapaPostulacion::EnRevision, $this->gente['jorge']));
        $this->en(12, '14:30', 'jorge', fn () => $this->programarYConfirmar('lorena', 'jorge', -9, '15:00'));
        $this->en(9, '16:00', 'jorge', fn () => $this->completar('lorena'));
        $this->en(8, '11:15', 'jorge', fn () => $this->mover($this->postulaciones['karen'], EtapaPostulacion::Oferta, $this->gente['jorge']));
        $this->en(7, '11:15', 'jorge', fn () => $this->evaluar($this->gente['jorge'], $this->postulaciones['lorena'], [7.8, 7.5, 8, 8], 'Domina SQL para optimizar rutas; le falta experiencia en comercio exterior.'));
        $this->en(2, '11:15', 'jorge', fn () => $this->entrevistas['oscar'] = $this->programar($this->gente['jorge'], $this->postulaciones['oscar'], $this->gente['jorge'], $this->dia(2, '10:00')));
        $this->en(2, '17:30', 'jorge', fn () => app(CambiarEstadoEntrevista::class)->confirmar($this->entrevistas['oscar']));
    }

    /** Brío Retail (Pro): cuatro vacantes, una contratación, un retiro y procesos en marcha. */
    private function agendarBrio(): void
    {
        $this->en(52, '08:30', null, function () {
            $this->empresas['brio'] = $this->empresa('Brío Retail', PlanEmpresa::Pro, 'Retail', ['perfil' => true, 'vacante' => false, 'invitar' => true]);
            $this->gente['adminBrio'] = $this->usuario('Brío Retail — Admin', 'admin@brioretail.pe', Rol::Empresa, $this->empresas['brio']);
            $this->gente['rocio'] = $this->usuario('Rocío Quispe', 'rocio@brioretail.pe', Rol::Reclutador, $this->empresas['brio']);
        });

        // Ejecutivo Comercial B2B: contratan a Carla; Milagros retira su postulación.
        $this->en(50, '08:15', 'rocio', fn () => $this->vacantes['b2b'] = $this->vacante($this->gente['rocio'], 'Ejecutivo Comercial B2B', 'Lima', Modalidad::Presencial,
            'Desarrollar la cartera de clientes corporativos de Brío con venta consultiva y relaciones de largo plazo.', [
                ['Ventas consultivas', TipoRequisito::Obligatorio, 5, 2],
                ['Negociación', TipoRequisito::Obligatorio, 4, null],
                ['CRM', TipoRequisito::Deseable, 3, null],
                ['Excel avanzado', TipoRequisito::Deseable, 2, null],
            ]));
        $this->en(49, '11:15', 'rocio', fn () => $this->postulaciones['carla'] = $this->registrarCandidato($this->gente['rocio'], $this->vacantes['b2b'], 'Carla Mendoza', 'carla.mendoza@correo.com', 'Ejecutiva Comercial B2B', ['Ventas consultivas']));
        $this->en(49, '15:15', 'rocio', fn () => $this->postulaciones['andrea'] = $this->registrarCandidato($this->gente['rocio'], $this->vacantes['b2b'], 'Andrea Villanueva', 'andrea.villanueva@correo.com', 'Ejecutiva de Cuentas', []));
        $this->postulantePostula(48, 'renzo', 'Renzo Paredes', 'renzo.paredes@correo.com', 'Vendedor de tienda', 'b2b');
        $this->en(47, '17:30', 'rocio', fn () => $this->postulaciones['diegoSalazar'] = $this->registrarCandidato($this->gente['rocio'], $this->vacantes['b2b'], 'Diego Salazar', 'diego.salazar@correo.com', 'Practicante Comercial', []));

        $this->en(46, '08:15', 'rocio', fn () => $this->vacantes['marketing'] = $this->vacante($this->gente['rocio'], 'Analista de Marketing Digital', 'Lima', Modalidad::Hibrido,
            'Planificar y medir las campañas digitales de las tiendas y de la tienda online.', [
                ['Marketing digital', TipoRequisito::Obligatorio, 5, 2],
                ['Google Ads', TipoRequisito::Obligatorio, 4, null],
                ['Meta Ads', TipoRequisito::Deseable, 3, null],
                ['Analítica web', TipoRequisito::Obligatorio, 4, null],
                ['SEO', TipoRequisito::Deseable, 2, null],
            ]));
        $this->en(45, '11:15', 'rocio', fn () => $this->moverVarias(['carla' => EtapaPostulacion::EnRevision, 'andrea' => EtapaPostulacion::EnRevision, 'renzo' => EtapaPostulacion::Descartado], 'rocio'));
        $this->en(45, '15:15', 'rocio', fn () => $this->postulaciones['valeria'] = $this->registrarCandidato($this->gente['rocio'], $this->vacantes['marketing'], 'Valeria Castro', 'valeria.castro@correo.com', 'Analista de Marketing Digital', ['Google Ads']));
        $this->en(44, '16:15', 'rocio', fn () => $this->postulaciones['sebastian'] = $this->registrarCandidato($this->gente['rocio'], $this->vacantes['marketing'], 'Sebastián Ríos', 'sebastian.rios@correo.com', 'Community Manager', []));
        $this->en(43, '11:15', 'rocio', fn () => $this->programarYConfirmar('carla', 'rocio', -40, '11:00'));
        $this->en(43, '11:30', 'rocio', fn () => $this->programarYConfirmar('andrea', 'rocio', -39, '11:00'));
        $this->postulantePostula(42, 'milagros', 'Milagros Huamán', 'milagros.huaman@correo.com', 'Asesora Comercial', 'b2b');
        $this->en(41, '11:15', 'rocio', fn () => $this->postulaciones['joaquin'] = $this->registrarCandidato($this->gente['rocio'], $this->vacantes['marketing'], 'Joaquín Lara', 'joaquin.lara@correo.com', 'Asistente de Marketing', []));
        $this->en(41, '15:15', 'rocio', fn () => $this->postulaciones['fiorella'] = $this->registrarCandidato($this->gente['rocio'], $this->vacantes['marketing'], 'Fiorella Campos', 'fiorella.campos@correo.com', 'Diseñadora y Marketing', []));
        $this->en(40, '11:50', 'rocio', fn () => $this->completar('carla'));
        $this->en(39, '11:50', 'rocio', fn () => $this->completar('andrea'));
        $this->en(39, '15:15', 'rocio', function () {
            $this->evaluar($this->gente['rocio'], $this->postulaciones['carla'], [9, 9, 8.5, 9], 'Siete años de venta consultiva con cuentas grandes; negocia con datos y sin presionar.');
            $this->evaluar($this->gente['rocio'], $this->postulaciones['andrea'], [8, 8.5, 7.5, 8], 'Muy buena en el ciclo de venta de software; menos experiencia con productos físicos.');
        });
        $this->en(38, '15:15', 'rocio', fn () => $this->moverVarias([
            'milagros' => EtapaPostulacion::EnRevision,
            'valeria' => EtapaPostulacion::EnRevision,
            'sebastian' => EtapaPostulacion::EnRevision,
            'diegoSalazar' => EtapaPostulacion::Descartado,
            'joaquin' => EtapaPostulacion::Descartado,
            'fiorella' => EtapaPostulacion::Descartado,
        ], 'rocio'));
        $this->en(36, '11:15', 'rocio', fn () => $this->mover($this->postulaciones['carla'], EtapaPostulacion::Oferta, $this->gente['rocio']));

        // Jefe de Tienda en Arequipa.
        $this->en(35, '08:15', 'rocio', fn () => $this->vacantes['tienda'] = $this->vacante($this->gente['rocio'], 'Jefe de Tienda', 'Arequipa', Modalidad::Presencial,
            'Dirigir la tienda de Arequipa: equipo de ventas, inventarios y experiencia del cliente.', [
                ['Liderazgo de equipos', TipoRequisito::Obligatorio, 5, 3],
                ['Atención al cliente', TipoRequisito::Obligatorio, 4, null],
                ['Gestión de inventarios', TipoRequisito::Obligatorio, 4, null],
                ['Visual merchandising', TipoRequisito::Deseable, 2, null],
            ]));
        $this->en(34, '11:15', 'rocio', fn () => $this->postulaciones['ricardo'] = $this->registrarCandidato($this->gente['rocio'], $this->vacantes['tienda'], 'Ricardo Flores', 'ricardo.flores@correo.com', 'Supervisor de Tienda', ['Liderazgo de equipos']));
        $this->postulantePostula(34, 'gianella', 'Gianella Torres', 'gianella.torres@correo.com', 'Subgerente de Tienda', 'tienda');
        $this->en(33, '15:15', 'rocio', fn () => $this->mover($this->postulaciones['carla'], EtapaPostulacion::Contratado, $this->gente['rocio']));
        $this->en(33, '15:30', 'rocio', fn () => $this->mover($this->postulaciones['andrea'], EtapaPostulacion::Descartado, $this->gente['rocio']));
        $this->en(33, '16:15', 'rocio', fn () => $this->programarYConfirmar('valeria', 'rocio', -30, '15:00'));
        $this->en(32, '19:30', 'milagros', fn () => app(RetirarPostulacion::class)->ejecutar($this->postulaciones['milagros'], $this->gente['milagros']));
        $this->en(31, '11:15', 'rocio', fn () => $this->postulaciones['hector'] = $this->registrarCandidato($this->gente['rocio'], $this->vacantes['tienda'], 'Héctor Ramírez', 'hector.ramirez@correo.com', 'Cajero', []));
        $this->en(30, '16:00', 'rocio', fn () => $this->completar('valeria'));
        $this->en(29, '16:15', 'rocio', fn () => app(CambiarEstadoVacante::class)->ejecutar($this->vacantes['b2b']->fresh(), EstadoVacante::Cerrada));
        $this->en(28, '11:15', 'rocio', fn () => $this->evaluar($this->gente['rocio'], $this->postulaciones['valeria'], [8.8, 8.5, 8.6, 9], 'Mide cada campaña y sabe explicar el retorno; encaja muy bien con el equipo.'));
        $this->en(28, '11:30', 'rocio', fn () => $this->programarYConfirmar('sebastian', 'rocio', -25, '16:00'));
        $this->en(25, '16:50', 'rocio', fn () => $this->completar('sebastian'));
        $this->en(24, '11:15', 'rocio', fn () => $this->evaluar($this->gente['rocio'], $this->postulaciones['sebastian'], [7, 8, 7, 8], 'Muy creativo en redes; le falta experiencia con Google Ads y analítica.'));

        // Desarrollador Frontend remoto.
        $this->en(22, '08:15', 'rocio', fn () => $this->vacantes['frontend'] = $this->vacante($this->gente['rocio'], 'Desarrollador Frontend', 'Remoto', Modalidad::Remoto,
            'Construir la nueva tienda online de Brío con React y TypeScript, junto al equipo de diseño.', [
                ['React', TipoRequisito::Obligatorio, 5, 2],
                ['TypeScript', TipoRequisito::Obligatorio, 4, null],
                ['JavaScript', TipoRequisito::Obligatorio, 4, null],
                ['Figma', TipoRequisito::Deseable, 2, null],
                ['Git', TipoRequisito::Deseable, 2, null],
            ]));
        $this->en(21, '15:15', 'rocio', fn () => $this->postulaciones['camila'] = $this->registrarCandidato($this->gente['rocio'], $this->vacantes['frontend'], 'Camila Ortiz', 'camila.ortiz@correo.com', 'Desarrolladora Frontend', ['React']));
        $this->postulantePostula(21, 'luis', 'Luis Alberto Chávez', 'luis.chavez@correo.com', 'Desarrollador Frontend', 'frontend');
        $this->en(20, '15:15', 'rocio', fn () => $this->postulaciones['nicolas'] = $this->registrarCandidato($this->gente['rocio'], $this->vacantes['frontend'], 'Nicolás Benites', 'nicolas.benites@correo.com', 'Desarrollador Full Stack', []));
        $this->en(20, '16:15', 'rocio', fn () => $this->moverVarias(['ricardo' => EtapaPostulacion::EnRevision, 'gianella' => EtapaPostulacion::EnRevision, 'hector' => EtapaPostulacion::Descartado], 'rocio'));
        $this->en(19, '16:15', 'rocio', fn () => $this->postulaciones['ariana'] = $this->registrarCandidato($this->gente['rocio'], $this->vacantes['frontend'], 'Ariana Soto', 'ariana.soto@correo.com', 'Diseñadora UI', ['Figma']));
        $this->en(18, '11:15', 'rocio', fn () => $this->programarYConfirmar('ricardo', 'rocio', -15, '11:30'));
        $this->en(16, '16:15', 'rocio', fn () => $this->mover($this->postulaciones['valeria'], EtapaPostulacion::Oferta, $this->gente['rocio']));
        $this->en(15, '12:30', 'rocio', fn () => $this->completar('ricardo'));
        $this->en(14, '15:15', 'rocio', fn () => $this->evaluar($this->gente['rocio'], $this->postulaciones['ricardo'], [8.5, 8.8, 8.4, 9], 'Seis años liderando tiendas; mermas bajas y un buen plan para el equipo de Arequipa.'));
        $this->en(14, '16:15', 'rocio', fn () => $this->moverVarias(['camila' => EtapaPostulacion::EnRevision, 'luis' => EtapaPostulacion::EnRevision, 'nicolas' => EtapaPostulacion::EnRevision, 'ariana' => EtapaPostulacion::Descartado], 'rocio'));
        $this->en(11, '15:15', 'rocio', fn () => $this->programarYConfirmar('camila', 'rocio', -8, '15:00'));
        $this->en(8, '16:00', 'rocio', fn () => $this->completar('camila'));
        $this->en(7, '15:15', 'rocio', fn () => $this->evaluar($this->gente['rocio'], $this->postulaciones['camila'], [9.2, 8.5, 9, 8.8], 'Cinco años con React y TypeScript en comercio electrónico; cuida la accesibilidad.'));
        $this->en(6, '11:15', 'rocio', fn () => $this->entrevistas['gianella'] = $this->programar($this->gente['rocio'], $this->postulaciones['gianella'], $this->gente['rocio'], $this->dia(1, '11:00')));
        $this->en(5, '15:15', 'rocio', fn () => $this->entrevistas['luis'] = $this->programar($this->gente['rocio'], $this->postulaciones['luis'], $this->gente['rocio'], $this->dia(3, '16:00')));
        $this->en(4, '15:15', 'rocio', fn () => app(CambiarEstadoEntrevista::class)->confirmar($this->entrevistas['luis']));
        $this->en(3, '15:15', 'rocio', fn () => $this->mover($this->postulaciones['ricardo'], EtapaPostulacion::Oferta, $this->gente['rocio']));
    }

    /** Nuna Salud (Pro): contrata una enfermera, cierra la vacante y pide el plan Enterprise. */
    private function agendarNuna(): void
    {
        $this->en(47, '08:30', null, function () {
            $this->empresas['nuna'] = $this->empresa('Nuna Salud', PlanEmpresa::Pro, 'Salud', ['perfil' => true, 'vacante' => false, 'invitar' => true]);
            $this->gente['adminNuna'] = $this->usuario('Nuna Salud — Admin', 'admin@nunasalud.pe', Rol::Empresa, $this->empresas['nuna']);
            $this->gente['patricia'] = $this->usuario('Patricia Mendoza', 'patricia@nunasalud.pe', Rol::Reclutador, $this->empresas['nuna']);
        });

        // Enfermera(o): contratan a Ana Lucía y cierran con Pamela aún en revisión (se le avisa del cierre).
        $this->en(44, '08:15', 'patricia', fn () => $this->vacantes['enfermeria'] = $this->vacante($this->gente['patricia'], 'Enfermera(o) de Hospitalización', 'Lima', Modalidad::Presencial,
            'Cuidado de pacientes en el servicio de hospitalización de medicina interna, en turnos rotativos.', [
                ['Enfermería hospitalaria', TipoRequisito::Obligatorio, 5, 2],
                ['Colegiatura CEP', TipoRequisito::Obligatorio, 5, null],
                ['BLS', TipoRequisito::Obligatorio, 4, null],
                ['Atención de pacientes', TipoRequisito::Obligatorio, 4, null],
            ]));
        $this->en(43, '15:15', 'patricia', fn () => $this->postulaciones['analucia'] = $this->registrarCandidato($this->gente['patricia'], $this->vacantes['enfermeria'], 'Ana Lucía Ramos', 'analucia.ramos@correo.com', 'Licenciada en Enfermería', ['Enfermería hospitalaria']));
        $this->en(42, '15:15', 'patricia', fn () => $this->postulaciones['jessica'] = $this->registrarCandidato($this->gente['patricia'], $this->vacantes['enfermeria'], 'Jessica Huaroto', 'jessica.huaroto@correo.com', 'Licenciada en Enfermería', []));
        $this->postulantePostula(41, 'kevin', 'Kevin Morales', 'kevin.morales@correo.com', 'Técnico en Enfermería', 'enfermeria');
        $this->en(40, '15:15', 'patricia', fn () => $this->postulaciones['pamela'] = $this->registrarCandidato($this->gente['patricia'], $this->vacantes['enfermeria'], 'Pamela Rojas', 'pamela.rojas@correo.com', 'Licenciada en Enfermería', []));
        $this->en(38, '11:15', 'patricia', fn () => $this->moverVarias([
            'analucia' => EtapaPostulacion::EnRevision,
            'jessica' => EtapaPostulacion::EnRevision,
            'pamela' => EtapaPostulacion::EnRevision,
            'kevin' => EtapaPostulacion::Descartado,
        ], 'patricia'));
        $this->en(37, '11:15', 'patricia', fn () => $this->programarYConfirmar('analucia', 'patricia', -35, '10:00'));
        $this->en(37, '11:20', 'patricia', fn () => $this->programarYConfirmar('jessica', 'patricia', -35, '15:00'));
        $this->en(35, '11:00', 'patricia', fn () => $this->completar('analucia'));
        $this->en(35, '16:00', 'patricia', fn () => $this->completar('jessica'));
        $this->en(34, '15:15', 'patricia', function () {
            $this->evaluar($this->gente['patricia'], $this->postulaciones['analucia'], [9, 9.2, 8.8, 9.4], 'Ocho años en hospitalización y capacitadora en BLS; trato muy cercano con los pacientes.');
            $this->evaluar($this->gente['patricia'], $this->postulaciones['jessica'], [8, 8.2, 7.8, 8.5], 'Sólida en pediatría; menos experiencia con pacientes adultos.');
        });
        $this->en(32, '11:15', 'patricia', fn () => $this->mover($this->postulaciones['analucia'], EtapaPostulacion::Oferta, $this->gente['patricia']));
        $this->en(30, '17:15', 'patricia', fn () => $this->mover($this->postulaciones['analucia'], EtapaPostulacion::Contratado, $this->gente['patricia']));
        $this->en(29, '17:15', 'patricia', fn () => $this->mover($this->postulaciones['jessica'], EtapaPostulacion::Descartado, $this->gente['patricia']));
        $this->en(29, '17:30', 'patricia', fn () => app(CambiarEstadoVacante::class)->ejecutar($this->vacantes['enfermeria']->fresh(), EstadoVacante::Cerrada));

        // Asistente de Recursos Humanos.
        $this->en(26, '08:15', 'patricia', fn () => $this->vacantes['rrhh'] = $this->vacante($this->gente['patricia'], 'Asistente de Recursos Humanos', 'Lima', Modalidad::Hibrido,
            'Apoyar la selección del personal asistencial y la elaboración de planillas.', [
                ['Reclutamiento y selección', TipoRequisito::Obligatorio, 5, null],
                ['Planillas', TipoRequisito::Obligatorio, 4, 1],
                ['Legislación laboral', TipoRequisito::Deseable, 3, null],
                ['Excel avanzado', TipoRequisito::Deseable, 2, null],
            ]));
        $this->en(25, '15:15', 'patricia', fn () => $this->postulaciones['daniela'] = $this->registrarCandidato($this->gente['patricia'], $this->vacantes['rrhh'], 'Daniela Cárdenas', 'daniela.cardenas@correo.com', 'Analista de Recursos Humanos', ['Planillas']));
        $this->postulantePostula(24, 'mariana', 'Mariana Alarcón', 'mariana.alarcon@correo.com', 'Practicante de Recursos Humanos', 'rrhh');
        $this->en(23, '15:15', 'patricia', fn () => $this->postulaciones['jeanpierre'] = $this->registrarCandidato($this->gente['patricia'], $this->vacantes['rrhh'], 'Jean Pierre Soto', 'jeanpierre.soto@correo.com', 'Asistente Administrativo', []));

        // Contador General.
        $this->en(18, '08:15', 'patricia', fn () => $this->vacantes['contador'] = $this->vacante($this->gente['patricia'], 'Contador General', 'Lima', Modalidad::Presencial,
            'Responsable de la contabilidad, los estados financieros bajo NIIF y las obligaciones tributarias de la red de clínicas.', [
                ['Contabilidad', TipoRequisito::Obligatorio, 5, 4],
                ['NIIF', TipoRequisito::Obligatorio, 4, null],
                ['Tributación', TipoRequisito::Obligatorio, 4, null],
                ['SAP', TipoRequisito::Deseable, 3, null],
                ['Excel avanzado', TipoRequisito::Deseable, 2, null],
            ]));
        $this->en(17, '15:15', 'patricia', fn () => $this->postulaciones['fernando'] = $this->registrarCandidato($this->gente['patricia'], $this->vacantes['contador'], 'Fernando Gutiérrez', 'fernando.gutierrez@correo.com', 'Contador Público Colegiado', ['NIIF']));
        $this->en(17, '15:30', 'patricia', fn () => $this->moverVarias(['daniela' => EtapaPostulacion::EnRevision, 'mariana' => EtapaPostulacion::EnRevision, 'jeanpierre' => EtapaPostulacion::Descartado], 'patricia'));
        $this->en(16, '15:15', 'patricia', fn () => $this->postulaciones['silvia'] = $this->registrarCandidato($this->gente['patricia'], $this->vacantes['contador'], 'Silvia Paredes', 'silvia.paredes@correo.com', 'Contadora', []));
        $this->en(15, '15:15', 'patricia', fn () => $this->postulaciones['raul'] = $this->registrarCandidato($this->gente['patricia'], $this->vacantes['contador'], 'Raúl Inga', 'raul.inga@correo.com', 'Asistente Contable', []));
        $this->en(13, '15:15', 'patricia', fn () => $this->programarYConfirmar('daniela', 'patricia', -10, '15:00'));
        $this->en(13, '15:20', 'patricia', fn () => $this->programarYConfirmar('mariana', 'patricia', -10, '16:00'));
        $this->en(10, '15:50', 'patricia', fn () => $this->completar('daniela'));
        $this->en(10, '16:50', 'patricia', fn () => $this->completar('mariana'));
        $this->en(9, '15:15', 'patricia', function () {
            $this->evaluar($this->gente['patricia'], $this->postulaciones['daniela'], [8.8, 9, 8.5, 9], 'Conoce el PLAME y el régimen laboral; ya seleccionó personal de salud.');
            $this->evaluar($this->gente['patricia'], $this->postulaciones['mariana'], [7, 8.5, 7.2, 8.6], 'Muy buena entrevistadora; aún sin experiencia en planillas.');
        });
        $this->en(9, '16:15', 'patricia', fn () => $this->moverVarias(['fernando' => EtapaPostulacion::EnRevision, 'silvia' => EtapaPostulacion::EnRevision, 'raul' => EtapaPostulacion::Descartado], 'patricia'));
        $this->en(7, '15:30', 'patricia', fn () => $this->mover($this->postulaciones['daniela'], EtapaPostulacion::Oferta, $this->gente['patricia']));
        $this->en(6, '11:30', 'adminNuna', fn () => app(SolicitarCambioDePlan::class)->ejecutar($this->empresas['nuna']->fresh(), PlanEmpresa::Enterprise, $this->gente['adminNuna']));
        $this->en(5, '16:15', 'patricia', fn () => $this->entrevistas['fernando'] = $this->programar($this->gente['patricia'], $this->postulaciones['fernando'], $this->gente['patricia'], $this->dia(2, '10:00')));
        $this->en(4, '16:15', 'patricia', fn () => app(CambiarEstadoEntrevista::class)->confirmar($this->entrevistas['fernando']));
        $this->en(3, '16:15', 'patricia', fn () => $this->programar($this->gente['patricia'], $this->postulaciones['silvia'], $this->gente['patricia'], $this->dia(4, '11:00')));
    }

    /** Altura Café: se registra hace cinco días (está en la prueba del plan Pro) y publica su primera vacante. */
    private function agendarAlturaCafe(): void
    {
        $this->en(5, '19:00', null, function () {
            $gabriela = app(RegistrarEmpresa::class)->ejecutar(new RegistroEmpresaData('Altura Café', 'Gabriela Rojas', 'admin@alturacafe.pe', self::PASSWORD));
            $gabriela->forceFill(['email_verified_at' => now()])->save();
            $gabriela->empresa?->forceFill(['sector' => 'Gastronomía'])->save();
            $this->gente['gabriela'] = $gabriela;
        });

        $this->en(4, '08:15', 'gabriela', fn () => $this->vacantes['barista'] = $this->vacante($this->gente['gabriela'], 'Barista', 'Cusco', Modalidad::Presencial,
            'Preparar café peruano de especialidad y atender a nuestros clientes en la cafetería de San Blas.', [
                ['Barismo', TipoRequisito::Obligatorio, 5, 1],
                ['Atención al cliente', TipoRequisito::Obligatorio, 4, null],
                ['Manipulación de alimentos', TipoRequisito::Obligatorio, 3, null],
            ]));
        $this->postulantePostula(3, 'lucero', 'Lucero Mamani', 'lucero.mamani@correo.com', 'Barista', 'barista');
        $this->postulantePostula(2, 'thiago', 'Thiago Huillca', 'thiago.huillca@correo.com', 'Mozo', 'barista');
        $this->en(1, '08:15', 'gabriela', fn () => $this->mover($this->postulaciones['lucero'], EtapaPostulacion::EnRevision, $this->gente['gabriela']));
    }

    /** Una persona crea su cuenta de postulante, sube su CV y postula por su cuenta (por la tarde). */
    private function postulantePostula(int $haceDias, string $clave, string $nombre, string $email, string $titular, string $vacante): void
    {
        $this->en($haceDias, '19:10', null, fn () => $this->gente[$clave] = $this->registrarPostulante($nombre, $email, $titular));
        $this->en($haceDias, '19:15', $clave, fn () => app(SubirCv::class)->ejecutar($this->gente[$clave]->candidato()->firstOrFail(), $this->archivoCv($email), $this->gente[$clave]));
        $this->en($haceDias, '19:30', $clave, fn () => $this->postulaciones[$clave] = app(PostularmeAVacante::class)->ejecutar($this->gente[$clave], $this->vacantes[$vacante]->id));
    }

    /** @param  array<string, EtapaPostulacion>  $destinos  clave de la postulación => etapa */
    private function moverVarias(array $destinos, string $quien): void
    {
        foreach ($destinos as $clave => $etapa) {
            $this->mover($this->postulaciones[$clave], $etapa, $this->gente[$quien]);
        }
    }

    /** Programa la entrevista "en $dias días" (negativo: ya pasó cuando se celebre) y la confirma. */
    private function programarYConfirmar(string $clave, string $entrevistador, int $dias, string $hora): void
    {
        $quien = $this->gente[$entrevistador];
        $this->entrevistas[$clave] = $this->programar($quien, $this->postulaciones[$clave], $quien, $this->dia($dias, $hora));
        app(CambiarEstadoEntrevista::class)->confirmar($this->entrevistas[$clave]);
    }

    private function completar(string $clave): void
    {
        app(CambiarEstadoEntrevista::class)->completar($this->entrevistas[$clave]->fresh() ?? $this->entrevistas[$clave]);
    }
}

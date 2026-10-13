<?php

namespace Database\Seeders\Demo\Volumen;

use App\Enums\EstadoVacante;
use App\Enums\Modalidad;
use App\Enums\TipoRequisito;

/**
 * Las vacantes que se suman a cada empresa de la demostración para llegar a unas diez, con su volumen de
 * postulaciones según el tipo de puesto: en contratación masiva (cajeros, operarios, mozos) llegan
 * cientos; en perfiles técnicos de empresa, decenas; en cargos de especialidad o jefaturas, pocas.
 *
 * Referencias de mercado (promedio de postulaciones por vacante): ~244 en general (Greenhouse, 2025),
 * ~370 en tecnología (Employinc) y ~43 por puesto en retail (iCIMS, 2025). La demostración queda por debajo
 * de esos promedios a propósito: se ve poblada y fluida sin pasar horas sembrando.
 *
 * Las vacantes antiguas están cerradas (con su contratación) y las recientes abiertas, para que cada plan
 * respete su límite de vacantes activas.
 */
final class CatalogoDeVacantes
{
    /** Postulaciones (mínimo y máximo) por tipo de vacante. */
    public const VOLUMEN = [
        'masiva' => [120, 200],
        'alta' => [60, 90],
        'normal' => [35, 55],
        'nicho' => [15, 30],
    ];

    /**
     * @return array<string, list<array{titulo: string, ubicacion: string, modalidad: Modalidad, familias: list<string>, clase: string, estado: EstadoVacante, hace: int, descripcion: string, requisitos: list<array{0: string, 1: TipoRequisito, 2: int, 3: int|null}>}>>
     */
    public static function porEmpresa(): array
    {
        $o = TipoRequisito::Obligatorio;
        $d = TipoRequisito::Deseable;
        $cerrada = EstadoVacante::Cerrada;
        $abierta = EstadoVacante::Abierta;
        $revision = EstadoVacante::EnRevision;
        $hibrido = Modalidad::Hibrido;
        $remoto = Modalidad::Remoto;
        $presencial = Modalidad::Presencial;

        return [
            'Nimbus Corp' => [
                self::v('Desarrollador Full Stack', 'Lima', $hibrido, ['tech'], 'alta', $cerrada, 55, 'Construirás funcionalidades de punta a punta en nuestra plataforma SaaS, con foco en calidad y entrega continua.',
                    [['Node.js', $o, 4, 2], ['React', $o, 4, 2], ['TypeScript', $o, 3, 2], ['PostgreSQL', $d, 3, null], ['Docker', $d, 2, null], ['Git', $o, 2, null]]),
                self::v('Desarrollador PHP Laravel', 'Lima', $remoto, ['tech'], 'normal', $cerrada, 50, 'Mantendrás y evolucionarás los servicios web de nuestros clientes con Laravel, pruebas y buenas prácticas.',
                    [['PHP', $o, 5, 3], ['Laravel', $o, 5, 2], ['MySQL', $o, 3, null], ['Git', $o, 2, null], ['Docker', $d, 2, null]]),
                self::v('Scrum Master', 'Lima', $hibrido, ['tech'], 'nicho', $cerrada, 48, 'Acompañarás a dos equipos de producto en su cadencia ágil, removiendo impedimentos y mejorando su flujo.',
                    [['Scrum', $o, 5, 3], ['Liderazgo de equipos', $o, 4, 2], ['Comunicación', $o, 3, null], ['Inglés', $d, 2, null]]),
                self::v('Ingeniero de Datos', 'Lima', $remoto, ['tech'], 'alta', $abierta, 36, 'Diseñarás y operarás los pipelines que alimentan los tableros y modelos de la compañía.',
                    [['Python', $o, 5, 3], ['SQL', $o, 4, 3], ['PostgreSQL', $o, 3, 2], ['AWS', $d, 3, null], ['Docker', $d, 2, null], ['Machine Learning', $d, 2, null]]),
                self::v('Desarrollador Frontend React', 'Lima', $remoto, ['tech'], 'alta', $abierta, 30, 'Llevarás a producción interfaces accesibles y rápidas, junto al equipo de diseño.',
                    [['React', $o, 5, 3], ['JavaScript', $o, 4, 3], ['TypeScript', $o, 4, 2], ['Git', $o, 2, null], ['Figma', $d, 2, null], ['Inglés', $d, 2, null]]),
                self::v('Científico de Datos', 'Lima', $hibrido, ['tech'], 'alta', $abierta, 26, 'Construirás modelos predictivos explicables y los llevarás a producción con el equipo de datos.',
                    [['Python', $o, 5, 3], ['Machine Learning', $o, 5, 2], ['SQL', $o, 3, 2], ['Power BI', $d, 2, null], ['PostgreSQL', $d, 2, null]]),
                self::v('QA Automation Engineer', 'Lima', $hibrido, ['tech'], 'normal', $abierta, 21, 'Automatizarás las pruebas de regresión de nuestros productos y cuidarás la calidad en cada entrega.',
                    [['Pruebas automatizadas', $o, 5, 2], ['Cypress', $o, 4, 2], ['Selenium', $d, 3, null], ['Testing de APIs', $o, 3, null], ['SQL', $d, 2, null]]),
                self::v('Arquitecto de Software', 'Lima', $hibrido, ['tech'], 'nicho', $revision, 18, 'Definirás la arquitectura de nuestros servicios y guiarás las decisiones técnicas de cuatro equipos.',
                    [['Java', $o, 5, 6], ['Spring Boot', $o, 4, 5], ['AWS', $o, 4, 3], ['Kubernetes', $d, 3, null], ['Liderazgo de equipos', $o, 3, 3]]),
                self::v('Analista de Soporte TI', 'Lima', $presencial, ['tech', 'comercial'], 'normal', $abierta, 12, 'Darás soporte a usuarios internos y externos, resolviendo incidentes y documentando soluciones.',
                    [['Atención al cliente', $o, 4, 1], ['SQL', $d, 2, null], ['Comunicación', $o, 3, null], ['Inglés', $d, 2, null]]),
            ],
            'Vela Studio' => [
                self::v('Project Manager', 'Lima', $hibrido, ['diseno'], 'normal', $cerrada, 55, 'Coordinarás proyectos creativos de principio a fin, entre clientes, diseño y desarrollo.',
                    [['Scrum', $o, 4, 2], ['Liderazgo de equipos', $o, 4, 3], ['Comunicación', $o, 3, null], ['Inglés', $d, 2, null]]),
                self::v('Community Manager', 'Lima', $remoto, ['diseno', 'comercial'], 'alta', $cerrada, 52, 'Gestionarás la comunidad y el calendario de contenidos de nuestras marcas aliadas.',
                    [['Marketing digital', $o, 4, 2], ['Meta Ads', $o, 3, 1], ['Comunicación', $o, 4, null], ['Analítica web', $d, 2, null]]),
                self::v('Motion Designer', 'Lima', $remoto, ['diseno'], 'nicho', $cerrada, 45, 'Animarás piezas de marca y producto para campañas digitales.',
                    [['Prototipado', $o, 4, 2], ['Figma', $o, 3, 2], ['Design systems', $d, 2, null]]),
                self::v('UI Designer', 'Lima', $hibrido, ['diseno'], 'alta', $abierta, 34, 'Diseñarás interfaces consistentes sobre nuestro sistema de diseño, de la idea al prototipo.',
                    [['Figma', $o, 5, 2], ['Design systems', $o, 4, 2], ['Prototipado', $o, 3, 2], ['Research', $d, 2, null]]),
                self::v('Marketing Digital Specialist', 'Lima', $hibrido, ['diseno', 'comercial'], 'alta', $abierta, 28, 'Planificarás y optimizarás campañas pagadas y orgánicas con foco en resultados medibles.',
                    [['Marketing digital', $o, 5, 3], ['Google Ads', $o, 4, 2], ['Meta Ads', $o, 4, 2], ['Analítica web', $o, 3, 2], ['SEO', $d, 2, null]]),
                self::v('Frontend Developer', 'Lima', $remoto, ['diseno', 'tech'], 'normal', $abierta, 22, 'Implementarás los diseños del estudio con código limpio, accesible y performante.',
                    [['JavaScript', $o, 4, 2], ['React', $o, 4, 2], ['TypeScript', $d, 3, null], ['Figma', $d, 2, null]]),
                self::v('Content Strategist', 'Lima', $remoto, ['diseno'], 'normal', $abierta, 15, 'Definirás la estrategia de contenidos y el posicionamiento orgánico de nuestros clientes.',
                    [['SEO', $o, 4, 2], ['Marketing digital', $o, 4, 2], ['Comunicación', $o, 3, null], ['Analítica web', $d, 2, null]]),
                self::v('UX Researcher', 'Lima', $hibrido, ['diseno'], 'nicho', $abierta, 9, 'Investigarás a usuarios para orientar las decisiones de producto del estudio.',
                    [['Research', $o, 5, 3], ['Prototipado', $d, 2, null], ['Comunicación', $o, 3, null], ['Inglés', $d, 2, null]]),
                self::v('Brand Designer', 'Lima', $hibrido, ['diseno'], 'normal', $revision, 6, 'Crearás y cuidarás sistemas de identidad visual de marcas en crecimiento.',
                    [['Design systems', $o, 4, 2], ['Figma', $o, 4, 2], ['Research', $d, 2, null]]),
            ],
            'Brío Retail' => [
                self::v('Analista de Inventarios', 'Lima', $presencial, ['logistica', 'comercial'], 'normal', $cerrada, 55, 'Controlarás el stock de nuestras tiendas y propondrás reposiciones para evitar quiebres.',
                    [['Gestión de inventarios', $o, 5, 2], ['Excel avanzado', $o, 4, 2], ['SAP', $d, 2, null], ['Comunicación', $d, 2, null]]),
                self::v('Jefe de Zona', 'Lima', $presencial, ['comercial'], 'nicho', $cerrada, 50, 'Liderarás a los jefes de tienda de una zona, con metas de venta y experiencia de cliente.',
                    [['Liderazgo de equipos', $o, 5, 4], ['Ventas consultivas', $o, 4, 3], ['Visual merchandising', $d, 2, null], ['Excel avanzado', $o, 3, 2]]),
                self::v('Cajero(a)', 'Lima', $presencial, ['comercial'], 'masiva', $abierta, 30, 'Atenderás el punto de pago con rapidez y trato cordial, cuidando el cuadre de caja.',
                    [['Atención al cliente', $o, 5, 1], ['Comunicación', $o, 3, null], ['Excel avanzado', $d, 1, null]]),
                self::v('Asesor(a) de Tienda', 'Lima', $presencial, ['comercial'], 'masiva', $abierta, 26, 'Asesorarás a los clientes en el piso de venta, recomendando productos y cerrando ventas.',
                    [['Atención al cliente', $o, 5, 1], ['Ventas consultivas', $o, 4, 1], ['Visual merchandising', $d, 2, null], ['Comunicación', $o, 3, null]]),
                self::v('Supervisor(a) de Ventas', 'Lima', $presencial, ['comercial'], 'alta', $abierta, 17, 'Supervisarás el turno de venta, coordinando al equipo y la reposición de las góndolas.',
                    [['Liderazgo de equipos', $o, 5, 2], ['Atención al cliente', $o, 4, 2], ['Gestión de inventarios', $d, 3, null], ['Visual merchandising', $d, 2, null]]),
                self::v('Visual Merchandiser', 'Lima', $presencial, ['comercial'], 'normal', $abierta, 8, 'Montarás las exhibiciones de temporada para que cada tienda comunique la propuesta de marca.',
                    [['Visual merchandising', $o, 5, 2], ['Marketing digital', $d, 2, null], ['Comunicación', $o, 3, null]]),
            ],
            'Ruta Sur Logística' => [
                self::v('Montacarguista', 'Callao', $presencial, ['logistica'], 'masiva', $cerrada, 55, 'Operarás montacargas en el centro de distribución, cuidando la seguridad y el orden del almacén.',
                    [['Gestión de almacenes', $o, 5, 2], ['Gestión de inventarios', $d, 3, null], ['Comunicación', $d, 2, null]]),
                self::v('Asistente Administrativo', 'Lima', $presencial, ['administracion', 'logistica'], 'alta', $cerrada, 50, 'Darás soporte administrativo a la gerencia de operaciones: reportes, compras y documentación.',
                    [['Excel avanzado', $o, 4, 2], ['Comunicación', $o, 3, null], ['SAP', $d, 2, null]]),
                self::v('Coordinador(a) de Inventarios', 'Callao', $hibrido, ['logistica'], 'normal', $cerrada, 46, 'Coordinarás los conteos cíclicos y la conciliación de inventarios entre almacenes.',
                    [['Gestión de inventarios', $o, 5, 3], ['Gestión de almacenes', $o, 4, 2], ['SAP', $d, 3, null], ['Excel avanzado', $o, 3, 2]]),
                self::v('Jefe de Operaciones', 'Lima', $presencial, ['logistica'], 'nicho', $cerrada, 42, 'Dirigirás las operaciones de distribución de la zona sur con metas de nivel de servicio.',
                    [['Distribución', $o, 5, 5], ['Liderazgo de equipos', $o, 4, 4], ['Gestión de almacenes', $o, 3, 3], ['Power BI', $d, 2, null]]),
                self::v('Analista de Comercio Exterior', 'Lima', $hibrido, ['logistica'], 'normal', $cerrada, 38, 'Gestionarás importaciones y exportaciones de clientes, con documentación y aduanas al día.',
                    [['Comercio exterior', $o, 5, 3], ['Inglés', $o, 3, null], ['Excel avanzado', $o, 3, 2], ['SAP', $d, 2, null]]),
                self::v('Operario(a) de Almacén', 'Callao', $presencial, ['logistica'], 'masiva', $abierta, 19, 'Recibirás, ubicarás y despacharás mercadería en nuestro centro de distribución.',
                    [['Gestión de almacenes', $o, 5, 1], ['Gestión de inventarios', $d, 3, null], ['Comunicación', $d, 2, null]]),
                self::v('Supervisor(a) de Distribución', 'Lima', $presencial, ['logistica'], 'normal', $abierta, 9, 'Supervisarás las rutas de reparto, los tiempos de entrega y al equipo de choferes.',
                    [['Distribución', $o, 5, 3], ['Liderazgo de equipos', $o, 4, 2], ['Comunicación', $o, 3, null], ['Power BI', $d, 2, null]]),
            ],
            'Nuna Salud' => [
                self::v('Analista de Tesorería', 'Lima', $presencial, ['administracion'], 'normal', $cerrada, 55, 'Gestionarás pagos, conciliaciones bancarias y el flujo de caja de la clínica.',
                    [['Contabilidad', $o, 4, 3], ['Excel avanzado', $o, 4, 2], ['SAP', $d, 3, null], ['NIIF', $d, 2, null]]),
                self::v('Coordinador(a) de Calidad', 'Lima', $presencial, ['salud', 'administracion'], 'nicho', $cerrada, 50, 'Coordinarás los procesos de calidad y acreditación de los servicios asistenciales.',
                    [['Comunicación', $o, 3, null], ['Liderazgo de equipos', $o, 4, 3], ['Excel avanzado', $o, 3, 2], ['Atención de pacientes', $d, 2, null]]),
                self::v('Recepcionista Clínica', 'Lima', $presencial, ['salud'], 'alta', $cerrada, 46, 'Recibirás a pacientes, gestionarás citas y admisión con calidez y rapidez.',
                    [['Atención al cliente', $o, 5, 1], ['Comunicación', $o, 4, null], ['Excel avanzado', $d, 2, null]]),
                self::v('Jefe(a) de Enfermería', 'Lima', $presencial, ['salud'], 'nicho', $cerrada, 41, 'Liderarás al equipo de enfermería de hospitalización, con foco en seguridad del paciente.',
                    [['Enfermería hospitalaria', $o, 5, 6], ['Liderazgo de equipos', $o, 4, 3], ['Colegiatura CEP', $o, 4, null], ['BLS', $o, 3, null]]),
                self::v('Técnico(a) de Enfermería', 'Lima', $presencial, ['salud'], 'masiva', $abierta, 22, 'Apoyarás los cuidados asistenciales de enfermería en hospitalización y emergencia.',
                    [['Atención de pacientes', $o, 5, 1], ['BLS', $o, 4, null], ['Enfermería hospitalaria', $d, 3, null], ['Comunicación', $d, 2, null]]),
                self::v('Licenciado(a) en Enfermería UCI', 'Lima', $presencial, ['salud'], 'nicho', $abierta, 14, 'Brindarás cuidados críticos en la unidad de cuidados intensivos.',
                    [['Enfermería hospitalaria', $o, 5, 3], ['Colegiatura CEP', $o, 4, null], ['BLS', $o, 4, null], ['Atención de pacientes', $o, 3, 2]]),
                self::v('Analista de Planillas', 'Lima', $hibrido, ['administracion'], 'normal', $abierta, 7, 'Procesarás las planillas mensuales y las declaraciones laborales del personal asistencial.',
                    [['Planillas', $o, 5, 2], ['Legislación laboral', $o, 4, 2], ['Excel avanzado', $o, 3, 2]]),
            ],
            'Altura Café' => [
                self::v('Mozo(a)', 'Lima', $presencial, ['gastronomia'], 'masiva', $abierta, 4, 'Atenderás las mesas de nuestro local con calidez, rapidez y orden.',
                    [['Atención al cliente', $o, 5, 1], ['Manipulación de alimentos', $o, 3, null], ['Comunicación', $d, 2, null]]),
                self::v('Cajero(a) de Cafetería', 'Lima', $presencial, ['gastronomia', 'comercial'], 'alta', $abierta, 4, 'Cobrarás en caja y cuidarás la experiencia de cada cliente al pagar.',
                    [['Atención al cliente', $o, 5, 1], ['Comunicación', $o, 3, null]]),
                self::v('Jefe(a) de Local', 'Lima', $presencial, ['gastronomia'], 'normal', $abierta, 3, 'Dirigirás el local: equipo, abastecimiento y experiencia del cliente.',
                    [['Liderazgo de equipos', $o, 5, 3], ['Gestión de inventarios', $o, 3, 2], ['Atención al cliente', $o, 4, 2]]),
                self::v('Cocinero(a) de Línea', 'Lima', $presencial, ['gastronomia'], 'normal', $abierta, 3, 'Prepararás los platos de la carta siguiendo las recetas y las normas de higiene.',
                    [['Manipulación de alimentos', $o, 5, 2], ['Comunicación', $d, 2, null], ['Gestión de inventarios', $d, 2, null]]),
                self::v('Pastelero(a)', 'Lima', $presencial, ['gastronomia'], 'normal', $abierta, 2, 'Elaborarás la repostería diaria del café con producto fresco y presentación cuidada.',
                    [['Manipulación de alimentos', $o, 5, 2], ['Gestión de inventarios', $d, 2, null]]),
                self::v('Asistente de Compras', 'Lima', $hibrido, ['gastronomia', 'administracion'], 'nicho', $abierta, 2, 'Gestionarás pedidos a proveedores y controlarás el abastecimiento de los locales.',
                    [['Gestión de inventarios', $o, 4, 2], ['Excel avanzado', $o, 3, 1], ['Comunicación', $d, 2, null]]),
            ],
        ];
    }

    /**
     * @param  list<string>  $familias
     * @param  list<array{0: string, 1: TipoRequisito, 2: int, 3: int|null}>  $requisitos
     * @return array{titulo: string, ubicacion: string, modalidad: Modalidad, familias: list<string>, clase: string, estado: EstadoVacante, hace: int, descripcion: string, requisitos: list<array{0: string, 1: TipoRequisito, 2: int, 3: int|null}>}
     */
    private static function v(string $titulo, string $ubicacion, Modalidad $modalidad, array $familias, string $clase, EstadoVacante $estado, int $hace, string $descripcion, array $requisitos): array
    {
        return compact('titulo', 'ubicacion', 'modalidad', 'familias', 'clase', 'estado', 'hace', 'descripcion', 'requisitos');
    }
}

<?php

namespace Database\Seeders\Demo\Volumen;

use Illuminate\Support\Carbon;

/**
 * Personas ficticias con un CV al estilo del de César Polo, para sembrar cientos
 * de postulaciones por vacante. Es reproducible: la misma semilla da las mismas
 * personas. Cada una tiene un perfil de su familia profesional con competencias
 * del catálogo, así el matching da resultados distintos sin porcentajes escritos
 * a mano.
 *
 * @phpstan-type Perfil array{titular: string, cargos: list<string>, carrera: string, skills: list<string>, herramientas: list<string>}
 */
final class GeneradorDePersonas
{
    private const MESES = ['Ene.', 'Feb.', 'Mar.', 'Abr.', 'May.', 'Jun.', 'Jul.', 'Ago.', 'Sep.', 'Oct.', 'Nov.', 'Dic.'];

    private const NOMBRES = ['María', 'José', 'Lucía', 'Carlos', 'Rosa', 'Jorge', 'Ana', 'Luis', 'Sofía', 'Miguel', 'Carmen', 'Diego', 'Valeria', 'Andrés', 'Fiorella',
        'Renzo', 'Milagros', 'Kevin', 'Gianella', 'Daniel', 'Karen', 'Marco', 'Paola', 'Julio', 'Elena', 'Ricardo', 'Patricia', 'Fernando', 'Claudia', 'Raúl',
        'Mariana', 'Javier', 'Daniela', 'Sergio', 'Jimena', 'Pedro', 'Camila', 'Alonso', 'Natalia', 'Gabriel', 'Lorena', 'Mauricio', 'Yesenia', 'Hugo', 'Brenda',
        'Cristian', 'Antonella', 'Oscar', 'Rocío', 'Víctor', 'Silvia', 'Franco', 'Noelia', 'Iván', 'Melissa', 'Álvaro', 'Thalía', 'Joaquín', 'Estefany', 'Bruno'];

    private const APELLIDOS = ['Quispe', 'Flores', 'Sánchez', 'Ramírez', 'Torres', 'Huamán', 'Mendoza', 'Rojas', 'Vargas', 'Castillo', 'García', 'Rodríguez', 'López',
        'Díaz', 'Cruz', 'Gutiérrez', 'Paredes', 'Chávez', 'Espinoza', 'Morales', 'Ríos', 'Salazar', 'Herrera', 'Medina', 'Cáceres', 'Ccori', 'Mamani', 'Condori',
        'Alarcón', 'Valdivia', 'Palacios', 'Zegarra', 'Ponce', 'Bustamante', 'Aguilar', 'Salas', 'Carrasco', 'Cárdenas', 'Pizarro', 'Vega', 'Reyes', 'Campos',
        'Contreras', 'Ayala', 'Farfán', 'Navarro', 'Tapia', 'Roque', 'Villanueva', 'Zavala', 'Escobar', 'Lazo', 'Peña', 'Yupanqui', 'Delgado', 'Soto', 'Arce', 'Luna'];

    private const CIUDADES = ['Lima', 'Callao', 'Chimbote', 'Trujillo', 'Arequipa', 'Piura', 'Cusco', 'Huancayo', 'Chiclayo', 'Ica', 'Tacna', 'Cajamarca'];

    private const UNIVERSIDADES = ['Universidad Nacional del Santa', 'Universidad Nacional Mayor de San Marcos', 'Pontificia Universidad Católica del Perú', 'Universidad de Lima',
        'Universidad Nacional de Ingeniería', 'Universidad Peruana de Ciencias Aplicadas', 'Universidad Privada del Norte', 'Universidad César Vallejo',
        'Universidad San Ignacio de Loyola', 'Universidad Continental', 'Universidad Nacional de Trujillo', 'Universidad Católica San Pablo'];

    private const EMPRESAS_PREVIAS = ['Tecnologías Andinas S.A.C.', 'Grupo Pacífico', 'Inversiones del Norte', 'Servicios Integrales Lima', 'Corporación Altiplano', 'Distribuidora Sol',
        'Consultores Kuntur', 'Innova Perú', 'Operadora Costa Verde', 'Soluciones Inca', 'Grupo Mirador', 'Comercial Los Andes', 'Alianza Estratégica S.A.', 'Red Pacífico',
        'Industrias Tambo', 'Nuevo Horizonte S.A.C.', 'Servicios del Sur', 'Agencia Nazca', 'Textiles Chavín', 'Clínica San Gabriel', 'Café Montaña', 'Logística Rápida del Perú'];

    /** Acciones y resultados que dan forma a los logros (la competencia se menciona por su nombre). */
    private const ACCIONES = [
        'Utilicé {c} para optimizar los procesos del área y reducir tiempos de respuesta.',
        'Apliqué {c} en proyectos de cara al cliente, con seguimiento semanal de resultados.',
        'Implementé mejoras con {c} que redujeron en {n}% los errores y reprocesos.',
        'Lideré la adopción de {c} en el equipo, documentando buenas prácticas.',
        'Trabajé con {c} para entregar reportes y soluciones dentro de los plazos acordados.',
        'Coordiné actividades basadas en {c}, logrando un incremento de {n}% en la productividad.',
        'Capacité a nuevos integrantes en {c} y apoyé la mejora continua del proceso.',
    ];

    /**
     * Familias profesionales. `perfiles`: titular, cargos de la trayectoria, carrera y competencias del
     * catálogo (nombres exactos) que suele tener quien se dedica a eso; `herramientas`: otras que no son
     * competencias del catálogo.
     *
     * @var array<string, list<array{titular: string, cargos: list<string>, carrera: string, skills: list<string>, herramientas: list<string>}>>
     */
    private const FAMILIAS = [
        'tech' => [
            ['titular' => 'Desarrollador Backend', 'cargos' => ['Desarrollador Backend', 'Desarrollador de Software', 'Analista Programador'], 'carrera' => 'Ingeniería de Sistemas e Informática',
                'skills' => ['Java', 'Spring Boot', 'PostgreSQL', 'SQL', 'Git', 'Docker', 'Testing de APIs', 'Inglés', 'Scrum'], 'herramientas' => ['Maven', 'Postman', 'Jira', 'IntelliJ']],
            ['titular' => 'Ingeniero de Datos', 'cargos' => ['Ingeniero de Datos', 'Analista de Datos', 'Científico de Datos'], 'carrera' => 'Ingeniería de Sistemas',
                'skills' => ['Python', 'SQL', 'PostgreSQL', 'Machine Learning', 'Power BI', 'Excel avanzado', 'MongoDB', 'AWS', 'Git'], 'herramientas' => ['Pandas', 'Airflow', 'Jupyter', 'scikit-learn']],
            ['titular' => 'Desarrollador Frontend', 'cargos' => ['Desarrollador Frontend', 'Desarrollador Web', 'Desarrollador Full Stack'], 'carrera' => 'Ingeniería de Software',
                'skills' => ['JavaScript', 'TypeScript', 'React', 'Node.js', 'Git', 'Figma', 'Inglés', 'Scrum'], 'herramientas' => ['HTML5', 'CSS3', 'Tailwind', 'Vite']],
            ['titular' => 'Ingeniero DevOps', 'cargos' => ['Ingeniero DevOps', 'Administrador de Sistemas', 'Ingeniero de Nube'], 'carrera' => 'Ingeniería de Sistemas e Informática',
                'skills' => ['Docker', 'Kubernetes', 'Terraform', 'AWS', 'CI/CD', 'Git', 'Google Cloud', 'Python'], 'herramientas' => ['Linux', 'Grafana', 'Prometheus', 'GitHub Actions']],
            ['titular' => 'QA Automation Engineer', 'cargos' => ['QA Engineer', 'Analista de Calidad', 'Tester Automatizador'], 'carrera' => 'Ingeniería de Sistemas',
                'skills' => ['Selenium', 'Cypress', 'Pruebas automatizadas', 'Testing de APIs', 'SQL', 'Scrum', 'Git'], 'herramientas' => ['Postman', 'Jira', 'TestRail', 'JMeter']],
            ['titular' => 'Desarrollador PHP Laravel', 'cargos' => ['Desarrollador PHP', 'Desarrollador Web', 'Programador'], 'carrera' => 'Computación e Informática',
                'skills' => ['PHP', 'Laravel', 'MySQL', 'JavaScript', 'Git', 'Docker', 'Testing de APIs'], 'herramientas' => ['Composer', 'Livewire', 'Redis', 'Blade']],
            ['titular' => 'Líder de Proyectos de TI', 'cargos' => ['Scrum Master', 'Jefe de Proyectos TI', 'Analista Funcional'], 'carrera' => 'Ingeniería Industrial y de Sistemas',
                'skills' => ['Scrum', 'Liderazgo de equipos', 'Comunicación', 'Inglés', 'Excel avanzado', 'SQL'], 'herramientas' => ['Jira', 'Confluence', 'MS Project', 'Miro']],
        ],
        'diseno' => [
            ['titular' => 'Diseñador UX/UI', 'cargos' => ['Diseñador UX/UI', 'Diseñador Digital', 'Diseñador Gráfico'], 'carrera' => 'Diseño Gráfico',
                'skills' => ['Figma', 'Design systems', 'Prototipado', 'Research', 'Comunicación', 'Inglés'], 'herramientas' => ['Adobe XD', 'Illustrator', 'Photoshop', 'Miro']],
            ['titular' => 'Especialista en Marketing Digital', 'cargos' => ['Especialista en Marketing Digital', 'Analista de Marketing', 'Community Manager'], 'carrera' => 'Marketing y Publicidad',
                'skills' => ['Marketing digital', 'Meta Ads', 'Google Ads', 'Analítica web', 'SEO', 'Comunicación', 'Excel avanzado'], 'herramientas' => ['Google Analytics', 'Canva', 'Mailchimp', 'Hootsuite']],
            ['titular' => 'Desarrollador Frontend', 'cargos' => ['Desarrollador Frontend', 'Desarrollador Web'], 'carrera' => 'Ingeniería de Software',
                'skills' => ['JavaScript', 'TypeScript', 'React', 'Figma', 'Git', 'Inglés'], 'herramientas' => ['HTML5', 'CSS3', 'Storybook', 'Vite']],
            ['titular' => 'Gestor de Proyectos Creativos', 'cargos' => ['Project Manager', 'Coordinador de Proyectos', 'Productor Digital'], 'carrera' => 'Administración de Empresas',
                'skills' => ['Scrum', 'Liderazgo de equipos', 'Comunicación', 'Inglés', 'Research'], 'herramientas' => ['Notion', 'Asana', 'Trello', 'Slack']],
        ],
        'comercial' => [
            ['titular' => 'Asesor Comercial', 'cargos' => ['Asesor de Ventas', 'Ejecutivo Comercial', 'Vendedor de Tienda'], 'carrera' => 'Administración de Empresas',
                'skills' => ['Atención al cliente', 'Ventas consultivas', 'Negociación', 'CRM', 'Comunicación'], 'herramientas' => ['Office 365', 'Salesforce', 'SAP Retail']],
            ['titular' => 'Supervisor de Tienda', 'cargos' => ['Supervisor de Tienda', 'Jefe de Tienda', 'Asistente de Tienda'], 'carrera' => 'Gestión Comercial',
                'skills' => ['Liderazgo de equipos', 'Visual merchandising', 'Atención al cliente', 'Gestión de inventarios', 'Excel avanzado', 'Comunicación'], 'herramientas' => ['POS', 'SAP Retail', 'Office 365']],
            ['titular' => 'Ejecutivo de Atención al Cliente', 'cargos' => ['Ejecutivo de Atención al Cliente', 'Asesor de Call Center', 'Cajero'], 'carrera' => 'Administración y Negocios Internacionales',
                'skills' => ['Atención al cliente', 'Comunicación', 'CRM', 'Excel avanzado', 'Inglés'], 'herramientas' => ['Zendesk', 'Office 365', 'WhatsApp Business']],
            ['titular' => 'Merchandiser', 'cargos' => ['Visual Merchandiser', 'Asistente de Marketing', 'Promotor de Ventas'], 'carrera' => 'Marketing',
                'skills' => ['Visual merchandising', 'Marketing digital', 'Atención al cliente', 'Comunicación', 'Meta Ads'], 'herramientas' => ['Canva', 'Photoshop', 'Excel']],
        ],
        'logistica' => [
            ['titular' => 'Analista de Logística', 'cargos' => ['Analista de Logística', 'Asistente de Almacén', 'Coordinador de Inventarios'], 'carrera' => 'Ingeniería Industrial',
                'skills' => ['Gestión de almacenes', 'Gestión de inventarios', 'Distribución', 'Excel avanzado', 'SAP', 'SQL'], 'herramientas' => ['WMS', 'Power Query', 'Office 365']],
            ['titular' => 'Supervisor de Operaciones', 'cargos' => ['Supervisor de Almacén', 'Jefe de Operaciones', 'Supervisor de Distribución'], 'carrera' => 'Gestión Logística',
                'skills' => ['Gestión de almacenes', 'Distribución', 'Liderazgo de equipos', 'Gestión de inventarios', 'Comunicación', 'Power BI'], 'herramientas' => ['WMS', 'Montacargas', 'RF Scanner']],
            ['titular' => 'Especialista en Comercio Exterior', 'cargos' => ['Analista de Comercio Exterior', 'Asistente de Aduanas', 'Coordinador de Importaciones'], 'carrera' => 'Negocios Internacionales',
                'skills' => ['Comercio exterior', 'Distribución', 'Inglés', 'Excel avanzado', 'SAP', 'Comunicación'], 'herramientas' => ['SUNAT Operatividad', 'Incoterms', 'Office 365']],
            ['titular' => 'Operario de Almacén', 'cargos' => ['Operario de Almacén', 'Auxiliar de Despacho', 'Montacarguista'], 'carrera' => 'Técnico en Logística',
                'skills' => ['Gestión de almacenes', 'Gestión de inventarios', 'Distribución', 'Comunicación'], 'herramientas' => ['RF Scanner', 'Montacargas', 'Paletizado']],
        ],
        'salud' => [
            ['titular' => 'Licenciada en Enfermería', 'cargos' => ['Enfermera de Hospitalización', 'Enfermera Asistencial', 'Enfermera de Emergencia'], 'carrera' => 'Enfermería',
                'skills' => ['Enfermería hospitalaria', 'Atención de pacientes', 'BLS', 'Colegiatura CEP', 'Comunicación', 'Liderazgo de equipos'], 'herramientas' => ['HIS', 'SIS', 'Bioseguridad']],
            ['titular' => 'Técnico(a) de Enfermería', 'cargos' => ['Técnico de Enfermería', 'Auxiliar Asistencial', 'Asistente de Consultorio'], 'carrera' => 'Técnico en Enfermería',
                'skills' => ['Atención de pacientes', 'BLS', 'Enfermería hospitalaria', 'Comunicación'], 'herramientas' => ['Bioseguridad', 'Triaje', 'HIS']],
            ['titular' => 'Asistente de Admisión', 'cargos' => ['Recepcionista Clínica', 'Asistente de Admisión', 'Asistente Administrativo'], 'carrera' => 'Administración',
                'skills' => ['Atención al cliente', 'Comunicación', 'Excel avanzado', 'Atención de pacientes'], 'herramientas' => ['HIS', 'Office 365', 'Facturación']],
        ],
        'administracion' => [
            ['titular' => 'Contador', 'cargos' => ['Contador General', 'Asistente Contable', 'Analista Contable'], 'carrera' => 'Contabilidad',
                'skills' => ['Contabilidad', 'NIIF', 'Tributación', 'SAP', 'Excel avanzado', 'Comunicación'], 'herramientas' => ['PLE', 'SUNAT SOL', 'Concar', 'Office 365']],
            ['titular' => 'Analista de Recursos Humanos', 'cargos' => ['Analista de RR. HH.', 'Asistente de Recursos Humanos', 'Analista de Planillas'], 'carrera' => 'Psicología Organizacional',
                'skills' => ['Planillas', 'Legislación laboral', 'Reclutamiento y selección', 'Excel avanzado', 'Comunicación'], 'herramientas' => ['PLAME', 'T-Registro', 'Buk', 'Office 365']],
            ['titular' => 'Analista de Tesorería', 'cargos' => ['Analista de Tesorería', 'Asistente Financiero', 'Analista de Cuentas por Pagar'], 'carrera' => 'Administración y Finanzas',
                'skills' => ['Contabilidad', 'Excel avanzado', 'SAP', 'NIIF', 'Power BI'], 'herramientas' => ['Banca por Internet', 'Office 365', 'Concar']],
        ],
        'gastronomia' => [
            ['titular' => 'Barista', 'cargos' => ['Barista', 'Asistente de Cafetería', 'Auxiliar de Barra'], 'carrera' => 'Técnico en Gastronomía',
                'skills' => ['Barismo', 'Atención al cliente', 'Manipulación de alimentos', 'Comunicación'], 'herramientas' => ['Máquina espresso', 'Latte art', 'POS']],
            ['titular' => 'Cocinero(a)', 'cargos' => ['Cocinero de Línea', 'Ayudante de Cocina', 'Pastelero'], 'carrera' => 'Gastronomía y Alta Cocina',
                'skills' => ['Manipulación de alimentos', 'Gestión de inventarios', 'Comunicación', 'Liderazgo de equipos'], 'herramientas' => ['HACCP', 'Mise en place', 'Panadería']],
            ['titular' => 'Atención en Salón', 'cargos' => ['Mozo', 'Cajero de Cafetería', 'Anfitrión'], 'carrera' => 'Hotelería y Turismo',
                'skills' => ['Atención al cliente', 'Comunicación', 'Ventas consultivas', 'Manipulación de alimentos'], 'herramientas' => ['POS', 'Office', 'Reservas']],
        ],
    ];

    private int $correlativo = 0;

    public function __construct(int $semilla = 20261014, private readonly Carbon $hoy = new Carbon('2026-10-14'))
    {
        mt_srand($semilla);
    }

    /**
     * @param  list<string>  $buscadas  Competencias que pide la vacante a la que va a postular: la persona se parece
     *                                  en parte a ella (perfil afín y una parte de lo que se pide), no por completo.
     * @return array{nombre: string, email: string, titular: string, ciudad: string, telefono: string, competencias: list<string>, texto: string, archivo: string, pdf: bool}
     */
    public function persona(string $familia, array $buscadas = []): array
    {
        $perfiles = self::FAMILIAS[$familia];
        $perfil = $this->perfilAfin($perfiles, $buscadas);

        $nombre = self::NOMBRES[mt_rand(0, count(self::NOMBRES) - 1)];
        $apellido1 = self::APELLIDOS[mt_rand(0, count(self::APELLIDOS) - 1)];
        $apellido2 = self::APELLIDOS[mt_rand(0, count(self::APELLIDOS) - 1)];
        $completo = "{$nombre} {$apellido1} {$apellido2}";
        $correlativo = ++$this->correlativo;
        $email = $this->slug("{$nombre}.{$apellido1}").$correlativo.'@correo.com';

        // Cada persona domina una parte de lo típico de su perfil (de 40 % a 95 %) y, a veces, algo de lo que pide la vacante.
        $nivel = mt_rand(40, 95);
        $conocidas = array_values(array_filter($perfil['skills'], fn () => mt_rand(1, 100) <= $nivel));
        foreach ($buscadas as $buscada) {
            if (! in_array($buscada, $conocidas, true) && ! in_array($buscada, $perfil['skills'], true) && mt_rand(1, 100) <= 15) {
                $conocidas[] = $buscada;
            }
        }
        if (count($conocidas) < 3) {
            $conocidas = array_values(array_unique([...$conocidas, ...array_slice($perfil['skills'], 0, 3)]));
        }
        shuffle($conocidas);
        $ciudad = self::CIUDADES[mt_rand(0, count(self::CIUDADES) - 1)];
        $telefono = '+51 9'.mt_rand(10, 99).' '.mt_rand(100, 999).' '.mt_rand(100, 999);
        $anios = mt_rand(1, 9);

        $texto = $this->texto($completo, $perfil, $conocidas, $ciudad, $telefono, $email, $anios);

        return [
            'nombre' => $completo,
            'email' => $email,
            'titular' => $perfil['titular'],
            'ciudad' => $ciudad,
            'telefono' => $telefono,
            'competencias' => $conocidas,
            'texto' => $texto,
            'archivo' => 'CV_'.str_replace(' ', '_', $this->sinTildes($completo)).(mt_rand(1, 10) <= 3 ? '.pdf' : '.docx'),
            'pdf' => false,
        ];
    }

    /**
     * El perfil de la familia que más se parece a lo que pide la vacante (con algo de azar: no todos postulan a lo que les calza).
     *
     * @param  list<array{titular: string, cargos: list<string>, carrera: string, skills: list<string>, herramientas: list<string>}>  $perfiles
     * @param  list<string>  $buscadas
     * @return array{titular: string, cargos: list<string>, carrera: string, skills: list<string>, herramientas: list<string>}
     */
    private function perfilAfin(array $perfiles, array $buscadas): array
    {
        $pesos = array_map(fn (array $p) => 1 + 3 * count(array_intersect($p['skills'], $buscadas)), $perfiles);
        $total = array_sum($pesos);
        $punto = mt_rand(1, max(1, $total));

        foreach ($perfiles as $i => $perfil) {
            $punto -= $pesos[$i];
            if ($punto <= 0) {
                return $perfil;
            }
        }

        return $perfiles[array_key_last($perfiles)];
    }

    /**
     * @param  Perfil  $perfil
     * @param  list<string>  $conocidas
     */
    private function texto(string $nombre, array $perfil, array $conocidas, string $ciudad, string $telefono, string $email, int $anios): string
    {
        $lineas = [
            mb_strtoupper($nombre),
            $perfil['carrera'],
            "{$ciudad}, Perú | {$telefono} | {$email} | linkedin.com/in/".$this->slug($nombre),
            '',
            'PERFIL PROFESIONAL',
            $this->perfil($perfil, $conocidas, $anios),
            '',
            'EXPERIENCIA PROFESIONAL',
        ];

        // Trayectoria hacia atrás desde hoy: el último puesto puede seguir vigente.
        $fin = $this->hoy->copy()->subMonths(mt_rand(0, 4));
        $vigente = mt_rand(1, 10) <= 4;
        $restante = max(12, $anios * 12);
        $puestos = min(3, max(1, intdiv($restante, 14)));

        for ($i = 0; $i < $puestos; $i++) {
            $meses = $i === $puestos - 1 ? max(4, $restante) : max(6, intdiv($restante, $puestos - $i) + mt_rand(-3, 4));
            $inicio = $fin->copy()->subMonths($meses);
            $cargo = $perfil['cargos'][min($i, count($perfil['cargos']) - 1)];
            $empresa = self::EMPRESAS_PREVIAS[mt_rand(0, count(self::EMPRESAS_PREVIAS) - 1)];
            $hasta = $i === 0 && $vigente ? 'Actual' : $this->mes($fin);

            $lineas[] = "{$cargo} | {$empresa} {$this->mes($inicio)} – {$hasta} (".$this->duracion($meses).')';

            foreach ($this->logros($conocidas) as $logro) {
                $lineas[] = $logro;
            }

            $fin = $inicio->copy()->subMonths(mt_rand(0, 2));
            $restante -= $meses;
            if ($restante < 4) {
                break;
            }
        }

        $lineas[] = '';
        $lineas[] = 'PROYECTOS PERSONALES';
        $lineas[] = $this->proyecto($perfil, $conocidas);
        $lineas[] = '';
        $lineas[] = 'EDUCACIÓN';
        $lineas[] = $perfil['carrera'];
        $egreso = (int) $this->hoy->format('Y') - mt_rand(0, 8);
        $lineas[] = self::UNIVERSIDADES[mt_rand(0, count(self::UNIVERSIDADES) - 1)].' | '.($egreso - 5).' • '.$egreso;
        if (in_array('Inglés', $conocidas, true)) {
            $lineas[] = 'Inglés — Nivel '.['Básico (A2)', 'Intermedio (B1)', 'Intermedio alto (B2)', 'Avanzado (C1)'][mt_rand(0, 3)];
        }
        $lineas[] = '';
        $lineas[] = 'HABILIDADES TÉCNICAS';
        foreach (array_chunk($conocidas, 3) as $grupo) {
            $lineas[] = implode(', ', $grupo).'.';
        }
        $lineas[] = '';
        $lineas[] = 'HERRAMIENTAS Y TECNOLOGÍAS';
        $extras = $perfil['herramientas'];
        shuffle($extras);
        $lineas[] = implode(' • ', array_merge($conocidas, array_slice($extras, 0, mt_rand(2, count($extras)))));

        return implode("\n", $lineas);
    }

    /**
     * @param  Perfil  $perfil
     * @param  list<string>  $conocidas
     */
    private function perfil(array $perfil, array $conocidas, int $anios): string
    {
        $principales = implode(', ', array_slice($conocidas, 0, 3));

        return "{$perfil['titular']} con {$anios} ".($anios === 1 ? 'año' : 'años')." de experiencia y orientación a resultados. Domino {$principales}"
            .' y busco aportar valor desde el primer día en un equipo que valore la mejora continua, la responsabilidad y el trabajo colaborativo.';
    }

    /**
     * @param  list<string>  $conocidas
     * @return list<string>
     */
    private function logros(array $conocidas): array
    {
        $elegidas = $conocidas;
        shuffle($elegidas);
        $logros = [];

        foreach (array_slice($elegidas, 0, mt_rand(2, 3)) as $competencia) {
            $plantilla = self::ACCIONES[mt_rand(0, count(self::ACCIONES) - 1)];
            $logros[] = str_replace(['{c}', '{n}'], [$competencia, (string) mt_rand(12, 45)], $plantilla);
        }

        return $logros;
    }

    /**
     * @param  Perfil  $perfil
     * @param  list<string>  $conocidas
     */
    private function proyecto(array $perfil, array $conocidas): string
    {
        $uso = $conocidas[mt_rand(0, count($conocidas) - 1)];

        return ['Proyecto de fin de carrera', 'Proyecto personal', 'Voluntariado profesional'][mt_rand(0, 2)]." — aplicación práctica de {$uso} en un caso real de {$perfil['carrera']}.";
    }

    private function mes(Carbon $fecha): string
    {
        return self::MESES[$fecha->month - 1].' '.$fecha->year;
    }

    private function duracion(int $meses): string
    {
        if ($meses < 12) {
            return $meses.' '.($meses === 1 ? 'mes' : 'meses');
        }

        $anios = intdiv($meses, 12);
        $resto = $meses % 12;

        return $anios.' '.($anios === 1 ? 'año' : 'años').($resto > 0 ? " {$resto} ".($resto === 1 ? 'mes' : 'meses') : '');
    }

    private function slug(string $texto): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', str_replace(' ', '.', $this->sinTildes($texto))) ?? '');
    }

    private function sinTildes(string $texto): string
    {
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);
    }
}

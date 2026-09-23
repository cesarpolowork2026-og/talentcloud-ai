<?php

namespace Database\Seeders\Demo;

/**
 * Textos de los CV de demostración. Están escritos para que el matching de
 * la semana 3 encuentre la evidencia que muestra el prototipo (por ejemplo,
 * Elena solo menciona Kubernetes como "interés en aprender" y no tiene
 * certificaciones cloud).
 */
class CvsDeDemostracion
{
    /** @var array<string, array{archivo: string, texto: string|null}> correo del candidato => CV */
    public const CVS = [
        'elena.ruiz@correo.com' => [
            'archivo' => 'CV_ElenaRuiz.docx',
            'texto' => <<<'TXT'
Elena Ruiz
Desarrolladora Backend · Lima · elena.ruiz@correo.com

Perfil
Desarrolladora backend con 5 años de experiencia con Java en proyectos backend de producción. Me enfoco en servicios confiables, pruebas automatizadas y buen diseño de datos.

Experiencia
Senior Backend Developer — Fintech Andina (2022 – actualidad)
Desarrollé APIs REST con Spring Boot en mi último puesto, para una plataforma de pagos de alto tráfico.
Diseñé esquemas y consultas optimizadas en PostgreSQL, reduciendo a la mitad el tiempo de los reportes.
Contenedoricé servicios con Docker y Docker Compose para los entornos de desarrollo y pruebas.

Backend Developer — Retail Digital (2020 – 2022)
Microservicios con Java 17 y Spring Boot, pruebas con JUnit y revisión de código en Git.

Formación
Ingeniería de Sistemas — Universidad Nacional Mayor de San Marcos.

Habilidades
Java, Spring Boot, PostgreSQL, Docker, Git, Scrum.

Intereses
Kubernetes (interés en aprender) y arquitectura en la nube.

Idiomas
Español nativo, inglés intermedio.
TXT,
        ],
        'sofia.nieto@correo.com' => [
            'archivo' => 'CV_SofiaNieto.docx',
            'texto' => <<<'TXT'
Sofía Nieto
Desarrolladora Backend · Remoto · sofia.nieto@correo.com

Perfil
Ingeniera de software con 6 años construyendo servicios backend en Java y operando plataformas en Kubernetes.

Experiencia
Backend Engineer — Nube Logística (2021 – actualidad)
APIs con Spring Boot y Java 21 para seguimiento de envíos en tiempo real.
Orquesté más de 40 microservicios con Kubernetes y Helm; imágenes con Docker y despliegues con GitHub Actions.
Modelado de datos en PostgreSQL y caché con Redis.

Software Engineer — Banca Móvil (2018 – 2021)
Servicios Java, integración con colas de mensajería y pruebas de carga.

Certificaciones
AWS Certified Developer – Associate (2024).

Habilidades
Java, Spring Boot, Docker, Kubernetes, PostgreSQL, AWS, CI/CD, inglés avanzado.
TXT,
        ],
        'marco@correo.com' => [
            'archivo' => 'CV_MarcoDiaz.pdf',
            'texto' => <<<'TXT'
Marco Díaz
Desarrollador Backend Junior · marco@correo.com

Perfil
Desarrollador backend con un año y medio de experiencia en Java, con ganas de crecer en equipos ágiles.

Experiencia
Desarrollador Junior — Soluciones Web SAC (2024 – actualidad)
Mantenimiento de servicios en Java y consultas en MySQL.
Participé en la migración de endpoints a Spring Boot junto al equipo senior.

Prácticas profesionales — Municipalidad Distrital (2023)
Reportes con SQL y automatización de tareas con scripts.

Formación
Computación e Informática — Instituto Tecnológico.

Habilidades
Java, MySQL, SQL, Git, Scrum.
TXT,
        ],
        'ivan.torres@correo.com' => [
            'archivo' => 'CV_IvanTorres.docx',
            'texto' => <<<'TXT'
Iván Torres
Diseñador de Producto · Madrid · ivan.torres@correo.com

Perfil
Diseñador de producto con 4 años de experiencia llevando ideas desde la investigación hasta interfaces en producción.

Experiencia
Product Designer — Estudio Norte (2022 – actualidad)
Diseño de flujos y prototipos de alta fidelidad en Figma para una aplicación de banca.
Research con usuarios: entrevistas, pruebas de usabilidad y síntesis de hallazgos.
Mantengo el sistema de diseño del producto junto al equipo de front-end.

UX Designer — Agencia Pixel (2020 – 2022)
Wireframes, prototipos y pruebas A/B para comercio electrónico.

Habilidades
Figma, research, prototipado, sistemas de diseño, nociones de Java y HTML.
TXT,
        ],
        'lucia.prat@correo.com' => [
            'archivo' => 'CV_LuciaPrat.docx',
            'texto' => <<<'TXT'
Lucía Prat
Analista de Datos · Remoto · lucia.prat@correo.com

Perfil
Analista de datos con 5 años de experiencia convirtiendo datos de negocio en decisiones.

Experiencia
Data Analyst — Comercial del Pacífico (2021 – actualidad)
Consultas SQL avanzadas sobre PostgreSQL y BigQuery; modelos de datos para ventas.
Automatización de reportes con Python (pandas) y tableros en Power BI para gerencia.

Analista de Inteligencia Comercial — Distribuidora Sur (2019 – 2021)
Reportes en Excel avanzado y SQL; segmentación de clientes.

Habilidades
SQL, Python, Power BI, Excel avanzado, estadística, inglés intermedio.
TXT,
        ],
        // PDF escaneado (sin texto seleccionable): reproduce "Error al analizar el CV".
        'pablo.vega@correo.com' => [
            'archivo' => 'CV_PabloVega.pdf',
            'texto' => null,
        ],
    ];
}

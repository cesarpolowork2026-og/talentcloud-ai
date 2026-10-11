<?php

namespace Database\Seeders\Demo;

/**
 * CV de demostración de Nimbus Corp y Vela Studio, escritos como CV reales
 * (contacto, perfil, puestos con mes y año, logros, formación, cursos e
 * idiomas) y con la evidencia que muestra el prototipo: por ejemplo, Elena
 * solo menciona Kubernetes como "interés en aprender" y no tiene
 * certificaciones cloud. Personas, empresas y teléfonos son ficticios.
 *
 * El servicio de IA evalúa y calibra sus umbrales con una versión anterior y
 * más breve de estos CV (talentcloud-ai/tests/datos/cvs), que se conserva sin
 * cambios como conjunto de evaluación.
 */
class CvsDeDemostracion
{
    /** @var array<string, array{archivo: string, texto: string|null}> correo del candidato => CV */
    public const CVS = [
        'elena.ruiz@correo.com' => [
            'archivo' => 'CV_ElenaRuiz.docx',
            'texto' => <<<'TXT'
Elena Ruiz Castañeda
Desarrolladora Backend Senior
+51 987 345 112 · elena.ruiz@correo.com · Magdalena del Mar, Lima

Perfil profesional
Desarrolladora backend con 5 años de experiencia con Java en proyectos de producción. Me enfoco en servicios confiables, pruebas automatizadas y buen diseño de datos.

Experiencia laboral
Senior Backend Developer — Fintech Andina S.A.C. | Sep 2022 – Actualidad
• Desarrollé APIs REST con Spring Boot para una plataforma de pagos que procesa 1,2 millones de transacciones al mes.
• Diseñé esquemas y consultas optimizadas en PostgreSQL, reduciendo a la mitad el tiempo de los reportes.
• Contenedoricé los servicios con Docker y Docker Compose para los entornos de desarrollo y pruebas.
• Acompaño a dos desarrolladores junior en revisiones de código.

Backend Developer — Retail Digital S.A.C. | Mar 2021 – Ago 2022
• Microservicios con Java 17 y Spring Boot para el catálogo y los pedidos de la tienda online.
• Pruebas unitarias con JUnit y revisión de código en Git.

Formación académica
Ingeniera de Sistemas — Universidad Nacional Mayor de San Marcos | 2015 – 2020

Cursos y certificaciones
Spring Boot avanzado: microservicios — Udemy (2023)

Idiomas
Inglés — intermedio (B2)

Habilidades
Java, Spring Boot, PostgreSQL, Docker, Git, JUnit, Scrum

Intereses
Kubernetes (interés en aprender) y arquitectura en la nube.

Referencias
Disponibles a solicitud.
TXT,
        ],
        'sofia.nieto@correo.com' => [
            'archivo' => 'CV_SofiaNieto.docx',
            'texto' => <<<'TXT'
Sofía Nieto Gallardo
Backend Engineer · Java y Kubernetes
+51 991 204 768 · sofia.nieto@correo.com · Trabajo remoto desde Arequipa

Perfil profesional
Ingeniera de software con 6 años construyendo servicios backend en Java y operando plataformas en Kubernetes. Me gusta automatizar todo lo que se repite.

Experiencia laboral
Backend Engineer — Nube Logística S.A.C. | Mar 2021 – Actualidad
• APIs con Spring Boot y Java 21 para el seguimiento de envíos en tiempo real.
• Orquesté más de 40 microservicios con Kubernetes y Helm; imágenes con Docker y despliegues con GitHub Actions.
• Modelado de datos en PostgreSQL y caché con Redis; la latencia del rastreo bajó de 900 ms a 180 ms.

Software Engineer — Banca Móvil S.A. | Ene 2020 – Feb 2021
• Servicios Java, integración con colas de mensajería y pruebas de carga.

Formación académica
Ingeniera de Software — Universidad Nacional de San Agustín de Arequipa | 2014 – 2019

Certificaciones
AWS Certified Developer – Associate (2024)
Certified Kubernetes Application Developer (2023)

Idiomas
Inglés — avanzado (C1)

Habilidades
Java, Spring Boot, Docker, Kubernetes, PostgreSQL, AWS, CI/CD

Referencias
Disponibles a solicitud.
TXT,
        ],
        'marco@correo.com' => [
            'archivo' => 'CV_MarcoDiaz.pdf',
            'texto' => <<<'TXT'
Marco Díaz Herrera
Desarrollador Backend Junior
+51 946 718 230 · marco@correo.com · Ate, Lima

Perfil
Desarrollador backend con un año y medio de experiencia en Java, con ganas de crecer en equipos ágiles y aprender de desarrolladores senior.

Experiencia laboral
Desarrollador Junior — Soluciones Web S.A.C. | Abr 2025 – Actualidad
• Mantenimiento de servicios en Java y consultas en MySQL para un sistema de reservas.
• Participé en la migración de endpoints a Spring Boot junto al equipo senior.

Practicante de Sistemas — Municipalidad Distrital de Ate | Mar 2024 – Dic 2024
• Reportes con SQL y automatización de tareas repetitivas con scripts.

Formación académica
Profesional técnico en Computación e Informática — Instituto Tecnológico IDAT | 2021 – 2024

Idiomas
Inglés — intermedio

Habilidades
Java, MySQL, SQL, Git, Scrum
TXT,
        ],
        'ivan.torres@correo.com' => [
            'archivo' => 'CV_IvanTorres.docx',
            'texto' => <<<'TXT'
Iván Torres Ochoa
Diseñador de Producto
+34 612 480 337 · ivan.torres@correo.com · Madrid, España

Perfil profesional
Diseñador de producto con 4 años de experiencia llevando ideas desde la investigación hasta interfaces en producción.

Experiencia laboral
Product Designer — Estudio Norte S.L. | Ene 2022 – Actualidad
• Diseño de flujos y prototipos de alta fidelidad en Figma para una aplicación de banca con 300 000 usuarios.
• Research con usuarios: entrevistas, pruebas de usabilidad y síntesis de hallazgos para el equipo de producto.
• Mantengo el sistema de diseño del producto junto al equipo de front-end.

UX Designer — Agencia Pixel S.L. | Sep 2021 – Dic 2021
• Wireframes, prototipos y pruebas A/B para comercio electrónico.

Formación académica
Grado en Diseño Multimedia y Gráfico — ESNE, Madrid | 2016 – 2020

Idiomas
Español — nativo, Inglés — avanzado (C1)

Habilidades
Figma, research, prototipado, sistemas de diseño, nociones de Java y HTML

Referencias
Disponibles a solicitud.
TXT,
        ],
        'lucia.prat@correo.com' => [
            'archivo' => 'CV_LuciaPrat.docx',
            'texto' => <<<'TXT'
Lucía Prat Vásquez
Analista de Datos
+51 978 562 104 · lucia.prat@correo.com · Trabajo remoto desde Trujillo

Perfil profesional
Analista de datos con 5 años de experiencia convirtiendo datos de negocio en decisiones, y 6 años de experiencia previa con Java como desarrolladora backend.

Experiencia laboral
Data Analyst — Comercial del Pacífico S.A.C. | Mar 2021 – Actualidad
• Consultas SQL avanzadas sobre PostgreSQL y BigQuery; modelos de datos para el área de ventas.
• Automatización de reportes con Python (pandas) y tableros en Power BI para la gerencia comercial.
• El tablero de rentabilidad por cliente se usa en el comité semanal de ventas.

Desarrolladora Backend — Fintech Andina S.A.C. | Mar 2015 – Feb 2021
• Servicios de pagos en Java con Spring Boot sobre PostgreSQL, desplegados con Docker en Kubernetes.
• Obtuve la certificación AWS Solutions Architect – Associate.

Formación académica
Ingeniera de Sistemas — Universidad Nacional de Trujillo | 2009 – 2014

Idiomas
Inglés — intermedio

Habilidades
SQL, Python, Power BI, Excel avanzado, Java, Spring Boot, Docker, Kubernetes

Referencias
Disponibles a solicitud.
TXT,
        ],
        // PDF escaneado (sin texto seleccionable): reproduce "Error al analizar el CV".
        'pablo.vega@correo.com' => [
            'archivo' => 'CV_PabloVega.pdf',
            'texto' => null,
        ],
    ];
}

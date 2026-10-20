<?php

namespace Database\Seeders\Demo;

/**
 * CV de demostración de las empresas que no son de tecnología (Brío Retail, Ruta
 * Sur Logística, Nuna Salud y Altura Café). Todo es ficticio. Cada vacante tiene
 * candidatos fuertes y débiles para que el análisis de requisitos muestre coincidencias,
 * menciones dudosas y requisitos no encontrados.
 *
 * Convenciones que espera el lector de CV: puesto y periodo en una sola línea
 * ("Cargo — Empresa | Mar 2021 – Actualidad"), logros con "•" y títulos de
 * sección habituales. Los años están calculados para 2026.
 */
class CvsDeOtrasEmpresas
{
    /** @var array<string, array{archivo: string, texto: string}> correo del candidato => CV */
    public const CVS = [
        // — Brío Retail · Ejecutivo Comercial B2B —
        'carla.mendoza@correo.com' => ['archivo' => 'CV_CarlaMendoza.pdf', 'texto' => <<<'TXT'
Carla Mendoza Salas
Ejecutiva Comercial B2B
+51 987 214 563 · carla.mendoza@correo.com · San Borja, Lima

Perfil profesional
Ejecutiva comercial con 7 años de experiencia en ventas consultivas a empresas de consumo masivo y retail. Construyo relaciones de largo plazo con las áreas de compras y cierro contratos anuales cuidando el margen.

Experiencia laboral
Ejecutiva de Cuentas Corporativas — Distribuidora Andina S.A.C. | Mar 2021 – Actualidad
• Gestiono una cartera de 60 clientes corporativos con ventas B2B y superé la cuota anual tres años seguidos (hasta 118 %).
• Lideré la negociación comercial de contratos marco con dos cadenas de supermercados por S/ 4,2 millones al año.
• Registro oportunidades, visitas y pronósticos de venta en Salesforce; mi pipeline es la base del forecast mensual del área.
• Capacité a tres ejecutivos nuevos en el proceso de venta consultiva de la empresa.

Asesora Comercial Corporativa — Grupo Textil Lima S.A. | Feb 2019 – Feb 2021
• Venta consultiva de uniformes corporativos a empresas industriales y de servicios.
• Abrí 25 cuentas nuevas en Lima Norte y Callao en dos años.
• Preparé reportes semanales de ventas con Excel avanzado (tablas dinámicas y BUSCARV).

Formación académica
Bachiller en Administración de Empresas — Universidad de Lima | 2013 – 2018

Cursos y certificaciones
Programa de Ventas Consultivas B2B — ESAN Graduate School of Business (2022)
Negociación efectiva — Centrum PUCP (2020)

Idiomas
Inglés — intermedio (B1)

Herramientas
Salesforce, Excel avanzado, Power BI (reportes), Microsoft Teams

Referencias
Disponibles a solicitud.
TXT],
        'renzo.paredes@correo.com' => ['archivo' => 'CV_RenzoParedes.docx', 'texto' => <<<'TXT'
Renzo Paredes Gómez
Vendedor de tienda
+51 954 330 182 · renzo.paredes@correo.com · Los Olivos, Lima

Perfil
Vendedor con 5 años de experiencia en tiendas por departamento y retail de electrodomésticos. Me motivan las metas diarias y el trato directo con el público.

Experiencia laboral
Vendedor — Tiendas Moda Total S.A.C. | Ago 2022 – Actualidad
• Atención al cliente en piso de venta, cambios y devoluciones.
• En 2024 fui vendedor del mes en cuatro ocasiones.
• Manejo de caja y cuadre al cierre del turno.

Promotor de Ventas — Electro Hogar Perú S.A. | Abr 2021 – Jul 2022
• Demostraciones de productos en módulos de centros comerciales.
• Cumplimiento de la meta mensual de unidades vendidas en 14 de 16 meses.

Formación académica
Técnico en Administración de Negocios — Instituto San Ignacio de Loyola | 2018 – 2021

Idiomas
Inglés — básico

Habilidades
Trabajo en equipo, manejo de caja, Microsoft Office.
TXT],
        'milagros.huaman@correo.com' => ['archivo' => 'CV_MilagrosHuaman.pdf', 'texto' => <<<'TXT'
Milagros Huamán Ríos
Asesora Comercial B2B
+51 962 418 905 · milagros.huaman@correo.com · Surquillo, Lima

Perfil profesional
Asesora comercial con 3 años de experiencia en ventas B2B de servicios para empresas. Organizada, constante en el seguimiento y orientada a cerrar contratos de renovación anual.

Experiencia laboral
Asesora Comercial — Servicios Integrales del Sur S.A.C. | Ene 2023 – Actualidad
• Prospecto y cierro contratos de limpieza industrial con pymes mediante venta consultiva.
• Participo en la negociación de renovaciones anuales; en 2025 renovamos el 85 % de la cartera.
• Elaboro propuestas económicas y presentaciones para comités de compra.

Formación académica
Bachiller en Marketing — Universidad de San Martín de Porres | 2017 – 2022

Cursos y certificaciones
Técnicas de prospección comercial — Cámara de Comercio de Lima (2023)

Idiomas
Inglés — intermedio

Herramientas
Excel, PowerPoint, Google Workspace

Referencias
Disponibles a solicitud.
TXT],
        'diego.salazar@correo.com' => ['archivo' => 'CV_DiegoSalazar.docx', 'texto' => <<<'TXT'
Diego Salazar Vega
Egresado de Administración
+51 945 667 210 · diego.salazar@correo.com · Jesús María, Lima

Perfil
Egresado de Administración con un año de prácticas en el área comercial de una importadora. Busco mi primer empleo como ejecutivo de ventas.

Formación académica
Bachiller en Administración — Universidad Ricardo Palma | 2019 – 2024

Experiencia laboral
Practicante Comercial — Importadora del Pacífico S.A.C. | Mar 2024 – Mar 2025
• Elaboré cotizaciones y reportes de ventas semanales con Excel avanzado.
• Apoyé a los ejecutivos en la preparación de visitas y en el seguimiento de pedidos.

Cursos y certificaciones
Excel avanzado para negocios — Cibertec (2023)

Idiomas
Inglés — intermedio

Intereses
CRM (interés en aprender Salesforce) y ventas a empresas.
TXT],
        'andrea.villanueva@correo.com' => ['archivo' => 'CV_AndreaVillanueva.pdf', 'texto' => <<<'TXT'
Andrea Villanueva Torres
Ejecutiva de Cuentas · Software para empresas
+51 981 552 047 · andrea.villanueva@correo.com · Miraflores, Lima

Perfil profesional
Ejecutiva con 4 años de experiencia en ventas B2B de software empresarial. Acompaño el ciclo completo: diagnóstico, demostración, propuesta y cierre.

Experiencia laboral
Ejecutiva de Cuentas — Soluciones Digitales Perú S.A.C. | Jun 2022 – Actualidad
• Gestiono el ciclo completo de venta consultiva de un ERP para medianas empresas; en 2025 cerré 38 contratos.
• Mantengo el pipeline actualizado en HubSpot y reporto el forecast quincenal a la gerencia.
• Negociación de precios y condiciones con gerencias de compras y de TI.

Analista Comercial — Soluciones Digitales Perú S.A.C. | Ago 2021 – May 2022
• Calificación de leads entrantes y agendamiento de demostraciones para el equipo de ventas.

Formación académica
Ingeniera Industrial — Universidad Nacional de Ingeniería | 2015 – 2020

Cursos y certificaciones
HubSpot Sales Software Certification — HubSpot Academy (2023)

Idiomas
Inglés — avanzado (C1)

Herramientas
HubSpot, Excel, Google Workspace, Notion

Referencias
Disponibles a solicitud.
TXT],

        // — Brío Retail · Analista de Marketing Digital —
        'valeria.castro@correo.com' => ['archivo' => 'CV_ValeriaCastro.docx', 'texto' => <<<'TXT'
Valeria Castro Núñez
Analista de Marketing Digital
+51 993 120 774 · valeria.castro@correo.com · Pueblo Libre, Lima

Perfil profesional
Analista con 5 años de experiencia en marketing digital para retail y comercio electrónico. Planifico campañas con objetivos de negocio y mido cada sol invertido.

Experiencia laboral
Analista de Marketing Digital — Mercado Express S.A.C. | Ene 2022 – Actualidad
• Gestiono campañas de Google Ads con un presupuesto mensual de S/ 80 000 y mejoré el retorno de la inversión en 30 %.
• Planifico campañas de Meta Ads para campañas de temporada (Día de la Madre, Cyber Wow, Navidad).
• Construí tableros de Google Analytics (GA4) y Looker Studio para medir conversiones de la tienda online.
• Coordino con la agencia de contenidos y con el equipo comercial el calendario de promociones.

Asistente de Marketing — Café Andino S.A.C. | Mar 2021 – Dic 2021
• Mejoré el posicionamiento web del blog con buenas prácticas de SEO: el tráfico orgánico creció 45 %.
• Publicación de anuncios y reportes mensuales de redes sociales.

Formación académica
Licenciada en Ciencias de la Comunicación — Pontificia Universidad Católica del Perú | 2015 – 2020

Cursos y certificaciones
Google Ads Search Certification — Google (2024)
Google Analytics Certification — Google (2023)

Idiomas
Inglés — avanzado

Herramientas
Google Ads, Meta Business Suite, Google Analytics 4, Looker Studio, Excel

Referencias
Disponibles a solicitud.
TXT],
        'sebastian.rios@correo.com' => ['archivo' => 'CV_SebastianRios.pdf', 'texto' => <<<'TXT'
Sebastián Ríos Paredes
Community Manager
+51 970 645 318 · sebastian.rios@correo.com · Barranco, Lima

Perfil
Community manager con 5 años de experiencia en marketing digital para marcas de consumo. Creativo, con buen manejo de comunidades y de pauta en redes sociales.

Experiencia laboral
Community Manager — Agencia Pulso S.A.C. | Ene 2022 – Actualidad
• Administro las redes de seis marcas de consumo masivo y sus campañas de Meta Ads e Instagram Ads.
• Elaboro calendarios de contenido y reportes mensuales de alcance e interacción.
• Una campaña de lanzamiento para una marca de snacks superó el millón de reproducciones orgánicas.

Asistente de Redes Sociales — Freelance | Mar 2021 – Dic 2021
• Contenido y respuesta a comentarios para emprendimientos de moda y gastronomía.

Formación académica
Profesional técnico en Marketing — Instituto Toulouse Lautrec | 2018 – 2021

Idiomas
Inglés — intermedio

Herramientas
Meta Business Suite, Canva, CapCut, Adobe Lightroom

Intereses
SEO (nociones) y Google Ads (interés en aprender).
TXT],
        'fiorella.campos@correo.com' => ['archivo' => 'CV_FiorellaCampos.docx', 'texto' => <<<'TXT'
Fiorella Campos León
Diseñadora gráfica
+51 948 772 603 · fiorella.campos@correo.com · San Miguel, Lima

Perfil
Diseñadora gráfica con 3 años de experiencia en piezas de marketing digital para emprendimientos y pequeñas marcas.

Experiencia laboral
Diseñadora Gráfica — Freelance | Feb 2023 – Actualidad
• Diseño de piezas para campañas de marketing digital en Figma e Illustrator.
• Apoyo en la publicación de anuncios en redes sociales de mis clientes.
• Manuales de marca para ocho emprendimientos.

Formación académica
Profesional técnica en Diseño Gráfico — Instituto Toulouse Lautrec | 2019 – 2022

Idiomas
Inglés — básico

Herramientas
Figma, Adobe Illustrator, Adobe Photoshop, Canva
TXT],
        'joaquin.lara@correo.com' => ['archivo' => 'CV_JoaquinLara.pdf', 'texto' => <<<'TXT'
Joaquín Lara Benavides
Asistente de Marketing
+51 949 801 256 · joaquin.lara@correo.com · Trujillo, La Libertad

Perfil
Asistente de marketing con 4 años de experiencia en activaciones y material para punto de venta en el norte del país.

Experiencia laboral
Asistente de Marketing — Bebidas del Norte S.A.C. | Feb 2022 – Actualidad
• Coordino activaciones BTL en supermercados y ferias de Trujillo, Chiclayo y Piura.
• Gestiono proveedores de material impreso y exhibidores para las campañas.
• Controlo el presupuesto de las activaciones en hojas de cálculo.

Formación académica
Bachiller en Administración y Marketing — Universidad Privada Antenor Orrego | 2016 – 2021

Idiomas
Inglés — básico

Habilidades
Activaciones BTL, coordinación con proveedores, licencia de conducir A-I.
TXT],

        // — Brío Retail · Jefe de Tienda —
        'ricardo.flores@correo.com' => ['archivo' => 'CV_RicardoFlores.docx', 'texto' => <<<'TXT'
Ricardo Flores Zegarra
Supervisor de Tienda · Retail
+51 959 263 418 · ricardo.flores@correo.com · Cayma, Arequipa

Perfil profesional
Supervisor de tienda con 6 años liderando equipos de venta y más de 8 años en retail de moda y calzado. Formo equipos comprometidos con la meta y cuido la operación de la tienda de punta a punta.

Experiencia laboral
Supervisor de Tienda — Calzados Misti S.A.C. | Abr 2020 – Actualidad
• Liderazgo de un equipo de 14 vendedores en dos turnos; la tienda cumplió su meta trimestral en 9 de los últimos 10 trimestres.
• Control de inventarios semanal con mermas por debajo del 1 % de la venta.
• Implementé un programa de capacitación en atención al cliente que subió la satisfacción de 82 % a 91 %.
• Armo las exhibiciones de visual merchandising de cada temporada con los lineamientos de la marca.

Vendedor Senior — Calzados Misti S.A.C. | Ene 2018 – Mar 2020
• En 2019 tuve el mejor ticket promedio de la tienda.

Formación académica
Bachiller en Administración de Empresas — Universidad Católica de Santa María | 2012 – 2017

Cursos y certificaciones
Gestión de tiendas retail — Centrum PUCP (2022)

Idiomas
Inglés — intermedio

Herramientas
Sistema de punto de venta, Excel, Google Sheets

Referencias
Disponibles a solicitud.
TXT],
        'gianella.torres@correo.com' => ['archivo' => 'CV_GianellaTorres.pdf', 'texto' => <<<'TXT'
Gianella Torres Cáceres
Subgerente de Tienda
+51 974 102 395 · gianella.torres@correo.com · Yanahuara, Arequipa

Perfil
Subgerente de tienda con 4 años de experiencia en tiendas de conveniencia de alto tránsito.

Experiencia laboral
Subgerente de Tienda — Mini Market 24 S.A.C. | Mar 2022 – Actualidad
• Gestión de equipos de turno (6 personas) y armado de horarios semanales.
• Conteos de inventarios y pedidos semanales a proveedores.
• Atención al cliente en horas punta y solución de reclamos en tienda.

Formación académica
Profesional técnica en Administración — Instituto Superior Tecnológico Arequipa | 2018 – 2021

Idiomas
Inglés — básico

Habilidades
Gestión de equipos, inventarios, atención al cliente, Excel intermedio.
TXT],
        'hector.ramirez@correo.com' => ['archivo' => 'CV_HectorRamirez.docx', 'texto' => <<<'TXT'
Héctor Ramírez Quispe
Cajero
+51 958 446 721 · hector.ramirez@correo.com · Paucarpata, Arequipa

Perfil
Cajero con 2 años de experiencia en supermercados. Responsable, puntual y con buen trato al público.

Experiencia laboral
Cajero — Supermercados Sur S.A. | Jun 2024 – Actualidad
• Atención al cliente en caja y cuadre de efectivo al cierre del turno sin diferencias.

Formación académica
Secundaria completa — I.E. Independencia Americana | 2019 – 2023

Cursos y certificaciones
Excel básico — SENATI (2024)
TXT],

        // — Brío Retail · Desarrollador Frontend —
        'camila.ortiz@correo.com' => ['archivo' => 'CV_CamilaOrtiz.pdf', 'texto' => <<<'TXT'
Camila Ortiz Medina
Desarrolladora Frontend
+51 986 731 402 · camila.ortiz@correo.com · Santiago de Surco, Lima

Perfil profesional
Desarrolladora frontend con 5 años de experiencia con React y TypeScript en comercio electrónico. Me importan el rendimiento, la accesibilidad y el trabajo cercano con diseño.

Experiencia laboral
Frontend Developer — Tienda Click S.A.C. | Feb 2022 – Actualidad
• Desarrollé la tienda online con React y TypeScript, con más de 200 000 visitas al mes.
• Construí una librería de componentes accesibles en JavaScript a partir de los diseños en Figma.
• Reduje el tiempo de carga del catálogo de 4,1 s a 1,6 s con carga diferida de imágenes.
• Revisión de código y flujo de ramas en Git con integración continua.

Frontend Developer Junior — Agencia Pixel S.A.C. | Mar 2021 – Ene 2022
• Sitios web con JavaScript y React para clientes de retail y educación.

Formación académica
Ingeniera de Software — Universidad Peruana de Ciencias Aplicadas | 2016 – 2021

Cursos y certificaciones
Web Accessibility (WCAG 2.2) — Platzi (2024)

Idiomas
Inglés — avanzado (C1)

Habilidades
React, TypeScript, JavaScript, Next.js, Tailwind CSS, Figma, Git, Jest

Referencias
Disponibles a solicitud.
TXT],
        'luis.chavez@correo.com' => ['archivo' => 'CV_LuisChavez.docx', 'texto' => <<<'TXT'
Luis Alberto Chávez Rojas
Desarrollador Frontend
+51 951 280 663 · luis.chavez@correo.com · San Juan de Lurigancho, Lima

Perfil
Desarrollador frontend con 3 años de experiencia con React y JavaScript en una startup de delivery.

Experiencia laboral
Desarrollador Frontend — Delivery Ya S.A.C. | Abr 2023 – Actualidad
• Desarrollé las pantallas del panel de pedidos con React.js y JavaScript.
• Integré APIs REST del equipo backend y manejo el repositorio en GitHub con Git.
• En 2025 corregí 120 incidencias reportadas por los restaurantes aliados.

Formación académica
Computación e Informática — Instituto Cibertec | 2019 – 2022

Idiomas
Inglés — intermedio

Habilidades
React, JavaScript, HTML, CSS, Git

Intereses
TypeScript (interés en aprender).
TXT],
        'nicolas.benites@correo.com' => ['archivo' => 'CV_NicolasBenites.pdf', 'texto' => <<<'TXT'
Nicolás Benites Arce
Desarrollador Full Stack
+51 966 504 871 · nicolas.benites@correo.com · Breña, Lima

Perfil
Desarrollador full stack con 4 años de experiencia con PHP, Laravel y JavaScript en sistemas administrativos.

Experiencia laboral
Desarrollador Full Stack — Software Contable S.A.C. | May 2022 – Actualidad
• Desarrollé los módulos de facturación electrónica con Laravel y vistas con JavaScript.
• Mantengo el código en Git y despliego en servidores Linux.

Desarrollador Web — Freelance | Ene 2022 – Abr 2022
• Sitios en WordPress para negocios locales.

Formación académica
Bachiller en Ingeniería de Sistemas — Universidad Nacional Federico Villarreal | 2016 – 2021

Idiomas
Inglés — intermedio

Habilidades
PHP, Laravel, JavaScript, MySQL, Git, Linux
TXT],
        'ariana.soto@correo.com' => ['archivo' => 'CV_ArianaSoto.docx', 'texto' => <<<'TXT'
Ariana Soto Delgado
Diseñadora UI
+51 977 315 096 · ariana.soto@correo.com · Lince, Lima

Perfil
Diseñadora de interfaces con 4 años de experiencia en Figma para aplicaciones móviles financieras.

Experiencia laboral
Diseñadora UI — Fintech Monedero S.A.C. | Ago 2022 – Actualidad
• Diseñé el sistema de diseño de la app en Figma (más de 150 componentes).
• Prototipos de alta fidelidad para las pruebas con usuarios del equipo de producto.

Diseñadora Gráfica Junior — Estudio Kuntur | Ene 2022 – Jul 2022
• Piezas para redes sociales y presentaciones.

Formación académica
Licenciada en Diseño Profesional Interactivo — Universidad Peruana de Ciencias Aplicadas | 2016 – 2021

Idiomas
Inglés — avanzado

Herramientas
Figma, FigJam, Adobe XD, Maze

Intereses
React (nociones) para colaborar mejor con el equipo de desarrollo.
TXT],

        // — Ruta Sur Logística · Jefe de Almacén —
        'manuel.quispe@correo.com' => ['archivo' => 'CV_ManuelQuispe.pdf', 'texto' => <<<'TXT'
Manuel Quispe Huarcaya
Jefe de Almacén · Centros de distribución
+51 990 457 128 · manuel.quispe@correo.com · Villa El Salvador, Lima

Perfil profesional
Jefe de almacén con 9 años de experiencia en centros de distribución de consumo masivo. Ordeno la operación con indicadores, procesos claros y un equipo que conoce sus metas.

Experiencia laboral
Jefe de Almacén — Distribuidora Lurín S.A.C. | Mar 2019 – Actualidad
• Gestión de almacenes de 12 000 m² con WMS y 40 operarios a cargo en tres turnos.
• Liderazgo de equipos de recepción, almacenamiento y despacho; rotación de personal reducida de 30 % a 12 % anual.
• Control de inventarios cíclicos con exactitud superior al 99 %.
• Reportes de productividad y costos por pallet con Excel avanzado.
• Implementé el programa de seguridad que llevó el centro a 400 días sin accidentes incapacitantes.

Supervisor de Almacén — Operador Logístico Callao S.A. | Feb 2017 – Feb 2019
• Recepción, almacenamiento y despacho de mercadería importada.
• Coordinación con agentes de aduanas para el retiro de la carga importada.

Formación académica
Ingeniero Industrial — Universidad Nacional del Callao | 2010 – 2015

Cursos y certificaciones
Diplomado en Gestión de la Cadena de Suministro — Universidad del Pacífico (2021)
Operación segura de montacargas para supervisores — SENATI (2018)

Idiomas
Inglés — intermedio

Herramientas
WMS (Manhattan), SAP MM (consultas), Excel avanzado, Power BI

Referencias
Disponibles a solicitud.
TXT],
        'rosa.condori@correo.com' => ['archivo' => 'CV_RosaCondori.docx', 'texto' => <<<'TXT'
Rosa Condori Mamani
Supervisora de Almacén
+51 953 778 214 · rosa.condori@correo.com · Ate, Lima

Perfil
Supervisora de almacén desde 2022, con más de 5 años en una distribuidora de repuestos automotrices.

Experiencia laboral
Supervisora de Almacén — Repuestos del Centro S.A.C. | Feb 2022 – Actualidad
• Conteos de inventarios mensuales y control de ingresos y salidas.
• Coordinación de un equipo de cinco operarios.
• Reportes de existencias en Excel para el área de compras.

Asistente de Almacén — Repuestos del Centro S.A.C. | Mar 2021 – Ene 2022
• Recepción de mercadería y registro de guías de remisión.

Formación académica
Profesional técnica en Administración Logística — Instituto IDAT | 2018 – 2021

Cursos y certificaciones
Metodología 5S — SENATI (2023)

Habilidades
Inventarios, Excel, orden y limpieza (5S).
TXT],
        'wilmer.ccori@correo.com' => ['archivo' => 'CV_WilmerCcori.pdf', 'texto' => <<<'TXT'
Wilmer Ccori Huamán
Operario de Almacén
+51 941 625 380 · wilmer.ccori@correo.com · Lurín, Lima

Perfil
Operario con 3 años de experiencia en carga, descarga y despacho de mercadería. Montacarguista certificado.

Experiencia laboral
Operario de Almacén — Bodegas del Sur S.A.C. | Ene 2023 – Actualidad
• Picking y despacho de pedidos para bodegas de Lima Sur.
• Manejo de montacargas con licencia vigente.

Formación académica
Secundaria completa — I.E. Fe y Alegría N.º 17 | 2015 – 2019

Cursos y certificaciones
Operador de montacargas — SENATI (2023)

Habilidades
Montacargas, picking, trabajo en equipo.
TXT],

        // — Ruta Sur Logística · Analista de Logística —
        'karen.espinoza@correo.com' => ['archivo' => 'CV_KarenEspinoza.docx', 'texto' => <<<'TXT'
Karen Espinoza Salinas
Analista de Logística
+51 989 336 547 · karen.espinoza@correo.com · Bellavista, Callao

Perfil profesional
Analista con 4 años de experiencia en distribución de última milla y comercio exterior. Me gusta resolver problemas de operación con datos.

Experiencia laboral
Analista de Logística — Importaciones Rímac S.A.C. | Mar 2022 – Actualidad
• Planifico rutas de reparto de última milla para 300 pedidos diarios; el costo por entrega bajó 18 %.
• Seguimiento de importaciones marítimas y coordinación con agentes de aduanas y navieras.
• Construí tableros de indicadores en Power BI (entregas a tiempo, costo por ruta, lead time de importación).
• Modelos de costos y presupuestos con Excel avanzado.

Formación académica
Bachiller en Ingeniería Industrial — Universidad de Lima | 2016 – 2021

Cursos y certificaciones
Gestión de importaciones y exportaciones — ADEX (2023)
Power BI para analistas — Data Academy Perú (2022)

Idiomas
Inglés — intermedio (B2)

Herramientas
Power BI, Excel avanzado, SQL (nociones), SAP (consultas)

Referencias
Disponibles a solicitud.
TXT],
        'bruno.medina@correo.com' => ['archivo' => 'CV_BrunoMedina.pdf', 'texto' => <<<'TXT'
Bruno Medina Alva
Asistente de Compras
+51 947 210 538 · bruno.medina@correo.com · Comas, Lima

Perfil
Asistente con 3 años de experiencia en compras de insumos para una fábrica de plásticos.

Experiencia laboral
Asistente de Compras — Plásticos Unidos S.A.C. | Feb 2023 – Actualidad
• Cotizaciones con proveedores nacionales y órdenes de compra en Excel.
• Seguimiento de entregas y conformidad de facturas.

Formación académica
Bachiller en Administración — Universidad Tecnológica del Perú | 2017 – 2022

Habilidades
Excel, negociación con proveedores, Microsoft Office.
TXT],
        'lorena.paz@correo.com' => ['archivo' => 'CV_LorenaPaz.docx', 'texto' => <<<'TXT'
Lorena Paz Gutiérrez
Analista de Operaciones
+51 972 485 163 · lorena.paz@correo.com · San Isidro, Lima

Perfil
Analista con 3 años de experiencia en operaciones de reparto urbano. Fuerte en análisis de datos.

Experiencia laboral
Analista de Operaciones — Courier Rápido S.A.C. | Feb 2023 – Actualidad
• Optimicé rutas de reparto con consultas SQL sobre el historial de entregas: 12 % menos kilómetros por pedido.
• Reportes diarios de cumplimiento en Excel avanzado para la gerencia de operaciones.
• Coordino a 25 repartidores tercerizados en Lima Moderna.

Formación académica
Bachiller en Ingeniería Industrial — Universidad Nacional Mayor de San Marcos | 2017 – 2022

Idiomas
Inglés — intermedio

Herramientas
SQL, Excel avanzado, Google Sheets, Tableau
TXT],
        'oscar.vilca@correo.com' => ['archivo' => 'CV_OscarVilca.pdf', 'texto' => <<<'TXT'
Óscar Vilca Ramos
Coordinador de Transporte
+51 964 318 702 · oscar.vilca@correo.com · Chorrillos, Lima

Perfil profesional
Coordinador con 6 años de experiencia en distribución a provincias para empresas de consumo masivo.

Experiencia laboral
Coordinador de Transporte — Transportes Andinos S.A.C. | Mar 2020 – Actualidad
• Programación de la distribución a 15 ciudades y control de la flota tercerizada (22 unidades).
• Indicadores de entregas a tiempo en Excel: en 2024 pasamos de 88 % a 95 %.
• Negociación de tarifas con transportistas.

Formación académica
Bachiller en Administración — Universidad Nacional del Centro del Perú | 2013 – 2018

Habilidades
Distribución, gestión de transportistas, Excel, licencia de conducir A-IIb.

Referencias
Disponibles a solicitud.
TXT],

        // — Nuna Salud · Enfermera(o) de Hospitalización —
        'analucia.ramos@correo.com' => ['archivo' => 'CV_AnaLuciaRamos.docx', 'texto' => <<<'TXT'
Ana Lucía Ramos Ccahuana
Licenciada en Enfermería
+51 985 602 731 · analucia.ramos@correo.com · La Molina, Lima

Perfil profesional
Enfermera con 8 años de experiencia en hospitalización de medicina interna y cirugía. Trato cercano con los pacientes y sus familias, y registros clínicos rigurosos.

Experiencia laboral
Enfermera Asistencial — Hospital Santa Rosa del Sur | Ene 2020 – Actualidad
• Atención en el servicio de hospitalización de 30 camas, con cuidado de pacientes posquirúrgicos y adultos mayores.
• Capacitadora interna en RCP y BLS para el personal del servicio.
• Integré el comité de prevención de caídas, que en 2023 redujo las caídas en 40 %.

Enfermera — Clínica San Gabriel | Ene 2018 – Dic 2019
• Cuidado del paciente adulto mayor, administración de medicamentos y curaciones.

Formación académica
Licenciada en Enfermería — Universidad Nacional Mayor de San Marcos | 2012 – 2017
Colegiatura CEP vigente (Colegio de Enfermeros del Perú).

Cursos y certificaciones
BLS Provider — American Heart Association (2025)
Especialización en Enfermería en Centro Quirúrgico (en curso) — Universidad Peruana Cayetano Heredia

Idiomas
Inglés — intermedio

Referencias
Disponibles a solicitud.
TXT],
        'jessica.huaroto@correo.com' => ['archivo' => 'CV_JessicaHuaroto.pdf', 'texto' => <<<'TXT'
Jessica Huaroto Vílchez
Licenciada en Enfermería
+51 976 213 584 · jessica.huaroto@correo.com · Los Olivos, Lima

Perfil
Enfermera con 4 años de experiencia en hospitalización pediátrica.

Experiencia laboral
Enfermera — Clínica Infantil Los Olivos | Feb 2022 – Actualidad
• Atención en hospitalización pediátrica y cuidado de pacientes en aislamiento.
• Educación a padres sobre cuidados en casa al alta.

Formación académica
Licenciada en Enfermería — Universidad Peruana Cayetano Heredia | 2015 – 2020
Colegiatura CEP vigente.

Cursos y certificaciones
RCP pediátrico — Sociedad Peruana de Pediatría (2024)

Idiomas
Inglés — básico
TXT],
        'kevin.morales@correo.com' => ['archivo' => 'CV_KevinMorales.docx', 'texto' => <<<'TXT'
Kevin Morales Ticona
Técnico en Enfermería
+51 963 741 029 · kevin.morales@correo.com · Independencia, Lima

Perfil
Técnico en enfermería con 5 años de experiencia en consultorios y tópicos.

Experiencia laboral
Técnico en Enfermería — Policlínico Vida | Mar 2021 – Actualidad
• Atención de pacientes en tópico, toma de signos vitales y apoyo en procedimientos menores.
• Control del material estéril y del botiquín de emergencias.

Formación académica
Técnico en Enfermería — Instituto Daniel Alcides Carrión | 2018 – 2020

Habilidades
Signos vitales, inyectables, trato amable.
TXT],
        'pamela.rojas@correo.com' => ['archivo' => 'CV_PamelaRojas.pdf', 'texto' => <<<'TXT'
Pamela Rojas Espinoza
Licenciada en Enfermería
+51 982 657 140 · pamela.rojas@correo.com · Magdalena del Mar, Lima

Perfil
Enfermera recién colegiada, con un año de internado hospitalario. Busco mi primer puesto asistencial.

Formación académica
Licenciada en Enfermería — Universidad Privada Norbert Wiener | 2019 – 2024
Colegiatura CEP vigente.

Experiencia laboral
Interna de Enfermería — Hospital Nacional Dos de Mayo | Ene 2024 – Dic 2024
• Rotación por hospitalización, emergencia y centro quirúrgico.
• Cuidado del paciente bajo supervisión de la enfermera jefa.

Cursos y certificaciones
BLS Provider — American Heart Association (2025)

Idiomas
Inglés — intermedio
TXT],

        // — Nuna Salud · Asistente de Recursos Humanos —
        'daniela.cardenas@correo.com' => ['archivo' => 'CV_DanielaCardenas.docx', 'texto' => <<<'TXT'
Daniela Cárdenas Lozano
Analista de Recursos Humanos
+51 991 274 806 · daniela.cardenas@correo.com · Jesús María, Lima

Perfil profesional
Analista con 4 años de experiencia en reclutamiento y planillas en el sector salud. Ordenada, confidencial y cercana con los colaboradores.

Experiencia laboral
Analista de Recursos Humanos — Laboratorios Unidos S.A. | Feb 2022 – Actualidad
• Reclutamiento y selección de personal técnico y administrativo: 25 ingresos por trimestre.
• Elaboración de planillas para 320 colaboradores y declaración mensual del PLAME.
• Aplicación de la normativa laboral en contratos, vacaciones y liquidaciones.
• Reportes de rotación y ausentismo con Excel avanzado.

Formación académica
Licenciada en Psicología Organizacional — Universidad San Ignacio de Loyola | 2016 – 2021

Cursos y certificaciones
Gestión de planillas y PLAME — Instituto Pacífico (2023)
Legislación laboral peruana — ESAN (2022)

Idiomas
Inglés — intermedio

Herramientas
Excel avanzado, Buk, Sistema de planillas Starsoft

Referencias
Disponibles a solicitud.
TXT],
        'jeanpierre.soto@correo.com' => ['archivo' => 'CV_JeanPierreSoto.pdf', 'texto' => <<<'TXT'
Jean Pierre Soto Quiroz
Asistente Administrativo
+51 955 893 412 · jeanpierre.soto@correo.com · Cercado de Lima

Perfil
Asistente administrativo con 2 años de experiencia en un estudio contable.

Experiencia laboral
Asistente Administrativo — Consultora Contable Lima S.A.C. | Ene 2024 – Actualidad
• Apoyo en el cálculo de la nómina de los clientes y archivo de legajos.
• Atención de llamadas y correspondencia.

Formación académica
Profesional técnico en Administración — Instituto Superior Tecnológico Lima | 2020 – 2023

Habilidades
Archivo, Excel, Microsoft Office.
TXT],
        'mariana.alarcon@correo.com' => ['archivo' => 'CV_MarianaAlarcon.docx', 'texto' => <<<'TXT'
Mariana Alarcón Pérez
Estudiante de Psicología
+51 984 506 317 · mariana.alarcon@correo.com · Santiago de Surco, Lima

Perfil
Estudiante de último año de Psicología con prácticas en selección de personal. Comunicativa y con interés en el área de gestión humana.

Formación académica
Psicología (décimo ciclo) — Universidad Femenina del Sagrado Corazón | 2022 – Actualidad

Experiencia laboral
Practicante de Recursos Humanos — Retail Express S.A.C. | Mar 2025 – Actualidad
• Apoyo en el reclutamiento: publicación de avisos, filtro de CV y coordinación de entrevistas.
• Aplicación de pruebas psicométricas a postulantes de tienda.

Idiomas
Inglés — intermedio

Habilidades
Selección de personal, entrevistas por competencias, comunicación.
TXT],

        // — Nuna Salud · Contador General —
        'fernando.gutierrez@correo.com' => ['archivo' => 'CV_FernandoGutierrez.pdf', 'texto' => <<<'TXT'
Fernando Gutiérrez Valdivia
Contador Público Colegiado
+51 994 120 653 · fernando.gutierrez@correo.com · San Borja, Lima

Perfil profesional
Contador con 8 años de experiencia en contabilidad general de empresas de servicios de salud. Cierres puntuales, estados financieros confiables y cero contingencias tributarias.

Experiencia laboral
Contador General — Grupo Médico Integral S.A.C. | Feb 2020 – Actualidad
• Cierres contables mensuales al quinto día hábil y estados financieros bajo NIIF.
• Declaraciones tributarias mensuales y anuales ante SUNAT para cuatro empresas del grupo.
• Implementé el módulo SAP FI junto con la consultora externa.
• Atendí dos fiscalizaciones de SUNAT sin reparos.

Contador — Estudio Contable Asociados | Mar 2018 – Ene 2020
• Contabilidad general de diez clientes y conciliaciones bancarias con Excel avanzado.

Formación académica
Contador Público — Universidad Nacional Mayor de San Marcos | 2011 – 2016
Colegiado en el Colegio de Contadores Públicos de Lima.

Cursos y certificaciones
Diplomado en NIIF — Colegio de Contadores Públicos de Lima (2021)
SAP FI para usuarios clave — SAP Training (2022)

Idiomas
Inglés — intermedio

Herramientas
SAP FI, Excel avanzado, PDT y SIRE de SUNAT

Referencias
Disponibles a solicitud.
TXT],
        'silvia.paredes@correo.com' => ['archivo' => 'CV_SilviaParedes.docx', 'texto' => <<<'TXT'
Silvia Paredes Huanca
Contadora
+51 969 431 287 · silvia.paredes@correo.com · Cercado de Lima

Perfil
Contadora con 5 años de experiencia en pequeñas empresas comerciales.

Experiencia laboral
Contadora — Comercial Pacífico S.A.C. | Feb 2021 – Actualidad
• Contabilidad general y declaraciones tributarias mensuales.
• Conciliaciones bancarias y de proveedores con Excel avanzado.
• Coordinación con el estudio externo para el cierre anual.

Formación académica
Contadora Pública — Universidad de San Martín de Porres | 2014 – 2019

Cursos y certificaciones
Tributación para pymes — Instituto Pacífico (2022)

Idiomas
Inglés — básico

Intereses
NIIF (nociones, curso por empezar).
TXT],
        'raul.inga@correo.com' => ['archivo' => 'CV_RaulInga.pdf', 'texto' => <<<'TXT'
Raúl Inga Ponce
Asistente Contable
+51 957 264 819 · raul.inga@correo.com · El Tambo, Huancayo

Perfil
Asistente contable con 3 años de experiencia en registro de compras y ventas.

Experiencia laboral
Asistente Contable — Ferretería El Constructor S.A.C. | Mar 2023 – Actualidad
• Registro de comprobantes de compras y ventas y apoyo en la contabilidad general.
• Archivo y control de la documentación contable.

Formación académica
Bachiller en Contabilidad — Universidad Continental | 2018 – 2023

Habilidades
Registro de comprobantes, Excel, Concar.
TXT],

        // — Altura Café · Barista —
        'lucero.mamani@correo.com' => ['archivo' => 'CV_LuceroMamani.docx', 'texto' => <<<'TXT'
Lucero Mamani Condori
Barista
+51 984 216 390 · lucero.mamani@correo.com · San Blas, Cusco

Perfil
Barista con 3 años de experiencia en cafeterías de especialidad. Me apasiona el café peruano y explicárselo a cada cliente.

Experiencia laboral
Barista — Café de la Plaza | Mar 2023 – Actualidad
• Preparación de espresso y métodos filtrados con café de especialidad de Cusco y Puno.
• Atención al cliente nacional y extranjero, en español e inglés.
• Aplicación de buenas prácticas de manufactura (BPM) en la barra y control de temperaturas.
• Calibración diaria del molino y la máquina.

Formación académica
Secundaria completa — I.E. Ciencias | 2016 – 2020

Cursos y certificaciones
Barismo profesional — Escuela de Café del Cusco (2022)
Manipulación de alimentos — Municipalidad del Cusco (2025)

Idiomas
Inglés — intermedio, Quechua — nativo
TXT],
        'thiago.huillca@correo.com' => ['archivo' => 'CV_ThiagoHuillca.pdf', 'texto' => <<<'TXT'
Thiago Huillca Quispe
Mozo
+51 973 580 214 · thiago.huillca@correo.com · Wanchaq, Cusco

Perfil
Mozo con 2 años de experiencia en restaurantes turísticos del centro histórico.

Experiencia laboral
Mozo — Restaurante Inti S.A.C. | Feb 2024 – Actualidad
• Atención al cliente en salón y toma de pedidos para grupos de turistas.

Formación académica
Secundaria completa — I.E. Clorinda Matto de Turner | 2018 – 2022

Idiomas
Inglés — básico

Intereses
Barismo (interés en aprender).
TXT],
    ];
}

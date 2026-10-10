<?php

namespace Database\Seeders\Demo;

/**
 * CV de demostración de las empresas que no son de tecnología (Brío Retail, Ruta Sur
 * Logística, Nuna Salud y Altura Café). Personas y textos ficticios. Cada vacante tiene
 * candidatos fuertes y débiles, para que el matching muestre de todo: coincidencias con
 * años, menciones dudosas ("interés en aprender", "nociones") y requisitos no encontrados.
 * Se separan de CvsDeDemostracion, cuyos textos replica la evaluación del servicio de IA.
 */
class CvsDeOtrasEmpresas
{
    /** @var array<string, array{archivo: string, texto: string}> correo del candidato => CV */
    public const CVS = [
        // — Brío Retail · Ejecutivo Comercial B2B —
        'carla.mendoza@correo.com' => ['archivo' => 'CV_CarlaMendoza.pdf', 'texto' => <<<'TXT'
Carla Mendoza
Ejecutiva Comercial B2B · Lima · carla.mendoza@correo.com

Perfil
Ejecutiva con 7 años de experiencia en ventas consultivas a empresas, enfocada en relaciones de largo plazo.

Experiencia
Ejecutiva de Cuentas Corporativas — Distribuidora Andina (2021 – actualidad)
Gestioné una cartera de 60 clientes corporativos con ventas B2B y superé la cuota anual tres años seguidos.
Lideré la negociación comercial de contratos marco con cadenas de supermercados.
Registré oportunidades y seguimiento de clientes en Salesforce.

Asesora Comercial — Grupo Textil Lima (2019 – 2021)
Venta consultiva de uniformes corporativos; reportes semanales con Excel avanzado (tablas dinámicas).

Formación
Administración de Empresas — Universidad de Lima.

Habilidades
Ventas consultivas, negociación, Salesforce, Excel avanzado, comunicación.
TXT],
        'renzo.paredes@correo.com' => ['archivo' => 'CV_RenzoParedes.docx', 'texto' => <<<'TXT'
Renzo Paredes
Vendedor de tienda · Lima · renzo.paredes@correo.com

Perfil
Vendedor con 5 años de experiencia en tiendas por departamento, orientado a metas diarias.

Experiencia
Vendedor — Tiendas Moda Total (2022 – actualidad)
Atención al cliente en piso de venta y manejo de caja.
Cumplimiento de metas mensuales de venta de la tienda.

Promotor de ventas — Electro Hogar (2021 – 2022)
Demostraciones de productos en módulos de centros comerciales.

Formación
Técnico en Administración — Instituto San Ignacio de Loyola.

Habilidades
Atención al cliente, trabajo en equipo, manejo de caja.
TXT],
        'milagros.huaman@correo.com' => ['archivo' => 'CV_MilagrosHuaman.pdf', 'texto' => <<<'TXT'
Milagros Huamán
Asesora Comercial · Lima · milagros.huaman@correo.com

Perfil
Asesora comercial con 3 años de experiencia en ventas B2B de servicios de limpieza industrial.

Experiencia
Asesora Comercial — Servicios Integrales del Sur (2023 – actualidad)
Prospecté y cerré contratos con pequeñas y medianas empresas mediante venta consultiva.
Participé en la negociación de renovaciones anuales.

Formación
Marketing — Universidad San Martín de Porres.

Habilidades
Ventas B2B, prospección, comunicación.
TXT],
        'diego.salazar@correo.com' => ['archivo' => 'CV_DiegoSalazar.docx', 'texto' => <<<'TXT'
Diego Salazar
Practicante Comercial · Lima · diego.salazar@correo.com

Perfil
Egresado de Administración con 1 año de prácticas en el área comercial.

Experiencia
Practicante Comercial — Importadora del Pacífico (2024 – 2025)
Elaboré cotizaciones y reportes de ventas con Excel avanzado.
Apoyé a los ejecutivos en la preparación de visitas a clientes.

Formación
Administración — Universidad Ricardo Palma.

Habilidades
Excel avanzado, organización.

Intereses
CRM (interés en aprender Salesforce).
TXT],
        'andrea.villanueva@correo.com' => ['archivo' => 'CV_AndreaVillanueva.pdf', 'texto' => <<<'TXT'
Andrea Villanueva
Ejecutiva de Cuentas · Lima · andrea.villanueva@correo.com

Perfil
Ejecutiva con 4 años de experiencia en ventas B2B de software para empresas.

Experiencia
Ejecutiva de Cuentas — Soluciones Digitales Perú (2022 – actualidad)
Gestioné el ciclo completo de venta consultiva, desde la demostración hasta el cierre.
Mantuve el pipeline de oportunidades actualizado en HubSpot.
Negociación de precios y condiciones con gerencias de compras.

Formación
Ingeniería Industrial — Universidad Nacional de Ingeniería.

Habilidades
Ventas B2B, HubSpot, negociación, inglés intermedio.
TXT],

        // — Brío Retail · Analista de Marketing Digital —
        'valeria.castro@correo.com' => ['archivo' => 'CV_ValeriaCastro.docx', 'texto' => <<<'TXT'
Valeria Castro
Analista de Marketing Digital · Lima · valeria.castro@correo.com

Perfil
Analista con 5 años de experiencia en marketing digital para retail y comercio electrónico.

Experiencia
Analista de Marketing Digital — Mercado Express (2022 – actualidad)
Gestioné campañas de Google Ads con un presupuesto mensual de 80 000 soles y mejoré el retorno un 30 %.
Planifiqué campañas de Meta Ads para lanzamientos de temporada.
Construí tableros de Google Analytics (GA4) para medir conversiones de la tienda online.

Asistente de Marketing — Café Andino (2021 – 2022)
Mejoré el posicionamiento web del blog con buenas prácticas de SEO.

Formación
Ciencias de la Comunicación — Pontificia Universidad Católica del Perú.

Habilidades
Marketing digital, Google Ads, Meta Ads, Google Analytics, SEO.
TXT],
        'sebastian.rios@correo.com' => ['archivo' => 'CV_SebastianRios.pdf', 'texto' => <<<'TXT'
Sebastián Ríos
Community Manager · Lima · sebastian.rios@correo.com

Perfil
Community manager con 4 años de experiencia en marketing digital para marcas de consumo.

Experiencia
Community Manager — Agencia Pulso (2022 – actualidad)
Administré las redes de seis marcas y sus campañas de Meta Ads e Instagram Ads.
Elaboré calendarios de contenido y reportes mensuales de alcance.

Formación
Marketing — Instituto Toulouse Lautrec.

Habilidades
Marketing digital, Meta Ads, redacción, fotografía.

Intereses
SEO (nociones) y Google Ads (interés en aprender).
TXT],
        'fiorella.campos@correo.com' => ['archivo' => 'CV_FiorellaCampos.docx', 'texto' => <<<'TXT'
Fiorella Campos
Diseñadora y Marketing · Lima · fiorella.campos@correo.com

Perfil
Diseñadora gráfica con 3 años de experiencia en marketing digital para emprendimientos.

Experiencia
Diseñadora Freelance (2023 – actualidad)
Diseño de piezas para campañas de marketing digital en Figma.
Apoyo en la publicación de anuncios en redes sociales.

Formación
Diseño Gráfico — Instituto Toulouse Lautrec.

Habilidades
Figma, marketing digital, Illustrator.
TXT],
        'joaquin.lara@correo.com' => ['archivo' => 'CV_JoaquinLara.pdf', 'texto' => <<<'TXT'
Joaquín Lara
Asistente de Marketing · Trujillo · joaquin.lara@correo.com

Perfil
Asistente de marketing con 4 años de experiencia en activaciones y material punto de venta.

Experiencia
Asistente de Marketing — Bebidas del Norte (2022 – actualidad)
Coordiné activaciones BTL en supermercados y ferias.
Gestioné proveedores de material impreso para las campañas.

Formación
Administración y Marketing — Universidad Privada Antenor Orrego.

Habilidades
Activaciones BTL, coordinación con proveedores.
TXT],

        // — Brío Retail · Jefe de Tienda —
        'ricardo.flores@correo.com' => ['archivo' => 'CV_RicardoFlores.docx', 'texto' => <<<'TXT'
Ricardo Flores
Supervisor de Tienda · Arequipa · ricardo.flores@correo.com

Perfil
Supervisor de tienda con 6 años de experiencia en retail de moda y calzado.

Experiencia
Supervisor de Tienda — Calzados Misti (2020 – actualidad)
Liderazgo de un equipo de 14 vendedores y cumplimiento de metas de venta trimestrales.
Control de inventarios semanal con mermas por debajo del 1 %.
Mejoré la atención al cliente con un programa de capacitación interna.
Armé exhibiciones de visual merchandising para cada temporada.

Formación
Administración de Empresas — Universidad Católica de Santa María.

Habilidades
Liderazgo de equipos, control de inventarios, atención al cliente, visual merchandising.
TXT],
        'gianella.torres@correo.com' => ['archivo' => 'CV_GianellaTorres.pdf', 'texto' => <<<'TXT'
Gianella Torres
Subgerente de Tienda · Arequipa · gianella.torres@correo.com

Perfil
Subgerente de tienda con 4 años de experiencia en tiendas de conveniencia.

Experiencia
Subgerente de Tienda — Mini Market 24 (2022 – actualidad)
Gestión de equipos de turno y armado de horarios.
Conteos de inventarios y pedidos a proveedores.
Atención al cliente en horas punta.

Formación
Técnica en Administración — Instituto Superior Tecnológico Arequipa.

Habilidades
Gestión de equipos, inventarios, atención al cliente.
TXT],
        'hector.ramirez@correo.com' => ['archivo' => 'CV_HectorRamirez.docx', 'texto' => <<<'TXT'
Héctor Ramírez
Cajero · Arequipa · hector.ramirez@correo.com

Perfil
Cajero con 2 años de experiencia en supermercados.

Experiencia
Cajero — Supermercados Sur (2024 – actualidad)
Atención al cliente en caja y cuadre de efectivo al cierre del turno.

Formación
Secundaria completa; curso de Excel básico.

Habilidades
Atención al cliente, manejo de caja.
TXT],

        // — Brío Retail · Desarrollador Frontend —
        'camila.ortiz@correo.com' => ['archivo' => 'CV_CamilaOrtiz.pdf', 'texto' => <<<'TXT'
Camila Ortiz
Desarrolladora Frontend · Lima · camila.ortiz@correo.com

Perfil
Desarrolladora frontend con 5 años de experiencia con React y TypeScript en comercio electrónico.

Experiencia
Frontend Developer — Tienda Click (2022 – actualidad)
Desarrollé la tienda online con React y TypeScript, con más de 200 000 visitas al mes.
Construí una librería de componentes accesibles en JavaScript a partir de los diseños en Figma.
Revisión de código y flujo de ramas en Git.

Frontend Developer Jr. — Agencia Pixel (2021 – 2022)
Sitios web con JavaScript y React para clientes de retail.

Formación
Ingeniería de Software — Universidad Peruana de Ciencias Aplicadas.

Habilidades
React, TypeScript, JavaScript, Figma, Git.
TXT],
        'luis.chavez@correo.com' => ['archivo' => 'CV_LuisChavez.docx', 'texto' => <<<'TXT'
Luis Alberto Chávez
Desarrollador Frontend · Lima · luis.chavez@correo.com

Perfil
Desarrollador frontend con 3 años de experiencia con React y JavaScript.

Experiencia
Desarrollador Frontend — Startup Delivery Ya (2023 – actualidad)
Desarrollé pantallas del panel de pedidos con React.js y JavaScript.
Integré APIs REST y manejé el repositorio en GitHub.

Formación
Computación e Informática — Instituto Cibertec.

Habilidades
React, JavaScript, Git, CSS.

Intereses
TypeScript (interés en aprender).
TXT],
        'nicolas.benites@correo.com' => ['archivo' => 'CV_NicolasBenites.pdf', 'texto' => <<<'TXT'
Nicolás Benites
Desarrollador Full Stack · Lima · nicolas.benites@correo.com

Perfil
Desarrollador full stack con 4 años de experiencia con PHP, Laravel y JavaScript.

Experiencia
Desarrollador Full Stack — Software Contable SAC (2022 – actualidad)
Desarrollé módulos de facturación con Laravel y vistas con JavaScript.
Mantuve el código en Git y desplegué en servidores Linux.

Formación
Ingeniería de Sistemas — Universidad Nacional Federico Villarreal.

Habilidades
PHP, Laravel, JavaScript, MySQL, Git.
TXT],
        'ariana.soto@correo.com' => ['archivo' => 'CV_ArianaSoto.docx', 'texto' => <<<'TXT'
Ariana Soto
Diseñadora UI · Lima · ariana.soto@correo.com

Perfil
Diseñadora de interfaces con 4 años de experiencia en Figma para aplicaciones móviles.

Experiencia
Diseñadora UI — Fintech Monedero (2022 – actualidad)
Diseñé el sistema de diseño de la app en Figma y prototipos de alta fidelidad.

Formación
Diseño Interactivo — Universidad Peruana de Ciencias Aplicadas.

Habilidades
Figma, prototipado, design systems.

Intereses
React (nociones) para colaborar mejor con el equipo de desarrollo.
TXT],

        // — Ruta Sur Logística · Jefe de Almacén —
        'manuel.quispe@correo.com' => ['archivo' => 'CV_ManuelQuispe.pdf', 'texto' => <<<'TXT'
Manuel Quispe
Jefe de Almacén · Lima · manuel.quispe@correo.com

Perfil
Jefe de almacén con 9 años de experiencia en centros de distribución de consumo masivo.

Experiencia
Jefe de Almacén — Distribuidora Lurín (2019 – actualidad)
Gestión de almacenes de 12 000 m² con WMS y 40 operarios a cargo.
Liderazgo de equipos en tres turnos y programas de seguridad.
Control de inventarios cíclicos con exactitud superior al 99 %.
Reportes de productividad con Excel avanzado.

Supervisor de Almacén — Operador Logístico Callao (2017 – 2019)
Recepción, almacenamiento y despacho de mercadería importada.

Formación
Ingeniería Industrial — Universidad Nacional del Callao.

Habilidades
Gestión de almacenes, WMS, control de inventarios, liderazgo, Excel avanzado.
TXT],
        'rosa.condori@correo.com' => ['archivo' => 'CV_RosaCondori.docx', 'texto' => <<<'TXT'
Rosa Condori
Supervisora de Almacén · Lima · rosa.condori@correo.com

Perfil
Supervisora con 4 años de experiencia en almacenes de repuestos.

Experiencia
Supervisora de Almacén — Repuestos del Centro (2022 – actualidad)
Conteos de inventarios y control de ingresos y salidas.
Coordinación de un equipo de cinco operarios.
Reportes de existencias en Excel.

Formación
Administración Logística — Instituto IDAT.

Habilidades
Inventarios, Excel, orden y limpieza (5S).
TXT],
        'wilmer.ccori@correo.com' => ['archivo' => 'CV_WilmerCcori.pdf', 'texto' => <<<'TXT'
Wilmer Ccori
Operario de Almacén · Lima · wilmer.ccori@correo.com

Perfil
Operario con 3 años de experiencia en carga, descarga y despacho.

Experiencia
Operario de Almacén — Bodegas del Sur (2023 – actualidad)
Picking y despacho de pedidos; manejo de montacargas con licencia vigente.

Formación
Secundaria completa; certificación de montacarguista.

Habilidades
Montacargas, picking, trabajo en equipo.
TXT],

        // — Ruta Sur Logística · Analista de Logística —
        'karen.espinoza@correo.com' => ['archivo' => 'CV_KarenEspinoza.docx', 'texto' => <<<'TXT'
Karen Espinoza
Analista de Logística · Lima · karen.espinoza@correo.com

Perfil
Analista con 4 años de experiencia en distribución y comercio exterior.

Experiencia
Analista de Logística — Importaciones Rímac (2022 – actualidad)
Planifiqué rutas de reparto de última milla para 300 pedidos diarios.
Seguimiento de importaciones y coordinación con agentes de aduanas.
Construí tableros de indicadores en Power BI y modelos de costos con Excel avanzado.

Formación
Ingeniería Industrial — Universidad de Lima.

Habilidades
Distribución, importaciones, Power BI, Excel avanzado, SQL básico.
TXT],
        'bruno.medina@correo.com' => ['archivo' => 'CV_BrunoMedina.pdf', 'texto' => <<<'TXT'
Bruno Medina
Asistente de Compras · Lima · bruno.medina@correo.com

Perfil
Asistente con 3 años de experiencia en compras de insumos.

Experiencia
Asistente de Compras — Plásticos Unidos (2023 – actualidad)
Cotizaciones con proveedores y órdenes de compra en Excel.

Formación
Administración — Universidad Tecnológica del Perú.

Habilidades
Excel, negociación con proveedores.
TXT],
        'lorena.paz@correo.com' => ['archivo' => 'CV_LorenaPaz.docx', 'texto' => <<<'TXT'
Lorena Paz
Analista de Operaciones · Lima · lorena.paz@correo.com

Perfil
Analista con 3 años de experiencia en operaciones de reparto.

Experiencia
Analista de Operaciones — Courier Rápido (2023 – actualidad)
Optimicé rutas de reparto con consultas SQL sobre el historial de entregas.
Reportes diarios de cumplimiento en Excel avanzado.

Formación
Ingeniería Industrial — Universidad Nacional Mayor de San Marcos.

Habilidades
Rutas de reparto, SQL, Excel avanzado.
TXT],
        'oscar.vilca@correo.com' => ['archivo' => 'CV_OscarVilca.pdf', 'texto' => <<<'TXT'
Óscar Vilca
Coordinador de Transporte · Lima · oscar.vilca@correo.com

Perfil
Coordinador con 6 años de experiencia en distribución a provincias.

Experiencia
Coordinador de Transporte — Transportes Andinos (2020 – actualidad)
Programación de la distribución a 15 ciudades y control de la flota tercerizada.
Indicadores de entregas a tiempo en Excel.

Formación
Administración — Universidad Nacional del Centro del Perú.

Habilidades
Distribución, gestión de transportistas, Excel.
TXT],

        // — Nuna Salud · Enfermera(o) de Hospitalización —
        'analucia.ramos@correo.com' => ['archivo' => 'CV_AnaLuciaRamos.docx', 'texto' => <<<'TXT'
Ana Lucía Ramos
Licenciada en Enfermería · Lima · analucia.ramos@correo.com

Perfil
Enfermera con 8 años de experiencia en hospitalización de medicina interna y cirugía.

Experiencia
Enfermera Asistencial — Hospital Santa Rosa del Sur (2020 – actualidad)
Atención en el servicio de hospitalización de 30 camas, con cuidado de pacientes posquirúrgicos.
Capacitadora interna en RCP y soporte vital básico (BLS).

Enfermera — Clínica San Gabriel (2018 – 2020)
Cuidado del paciente adulto mayor y administración de medicamentos.

Formación
Enfermería — Universidad Nacional Mayor de San Marcos.
Colegiatura CEP vigente (Colegio de Enfermeros del Perú).

Habilidades
Enfermería hospitalaria, BLS, cuidado de pacientes, registros clínicos.
TXT],
        'jessica.huaroto@correo.com' => ['archivo' => 'CV_JessicaHuaroto.pdf', 'texto' => <<<'TXT'
Jessica Huaroto
Licenciada en Enfermería · Lima · jessica.huaroto@correo.com

Perfil
Enfermera con 4 años de experiencia en hospitalización pediátrica.

Experiencia
Enfermera — Clínica Infantil Los Olivos (2022 – actualidad)
Atención en hospitalización pediátrica y cuidado de pacientes en aislamiento.
Certificada en RCP pediátrico.

Formación
Enfermería — Universidad Peruana Cayetano Heredia.
Colegiatura CEP vigente.

Habilidades
Hospitalización, RCP, atención de pacientes.
TXT],
        'kevin.morales@correo.com' => ['archivo' => 'CV_KevinMorales.docx', 'texto' => <<<'TXT'
Kevin Morales
Técnico en Enfermería · Lima · kevin.morales@correo.com

Perfil
Técnico en enfermería con 5 años de experiencia en consultorios y tópicos.

Experiencia
Técnico en Enfermería — Policlínico Vida (2021 – actualidad)
Atención de pacientes en tópico, toma de signos vitales y apoyo en procedimientos.

Formación
Técnico en Enfermería — Instituto Daniel Alcides Carrión.

Habilidades
Atención de pacientes, signos vitales, inyectables.
TXT],
        'pamela.rojas@correo.com' => ['archivo' => 'CV_PamelaRojas.pdf', 'texto' => <<<'TXT'
Pamela Rojas
Licenciada en Enfermería · Lima · pamela.rojas@correo.com

Perfil
Enfermera recién colegiada, con internado hospitalario de un año.

Experiencia
Interna de Enfermería — Hospital Nacional Dos de Mayo (2024 – 2025)
Rotación por hospitalización, emergencia y centro quirúrgico.

Formación
Enfermería — Universidad Norbert Wiener.
Colegiatura CEP vigente.

Habilidades
Cuidado del paciente, BLS, trabajo en equipo.
TXT],

        // — Nuna Salud · Asistente de Recursos Humanos —
        'daniela.cardenas@correo.com' => ['archivo' => 'CV_DanielaCardenas.docx', 'texto' => <<<'TXT'
Daniela Cárdenas
Analista de Recursos Humanos · Lima · daniela.cardenas@correo.com

Perfil
Analista con 4 años de experiencia en reclutamiento y planillas en el sector salud.

Experiencia
Analista de RR. HH. — Laboratorios Unidos (2022 – actualidad)
Reclutamiento y selección de personal técnico y administrativo, con 25 ingresos al trimestre.
Elaboración de planillas y declaración del PLAME.
Aplicación de la normativa laboral en contratos y liquidaciones.
Reportes de rotación con Excel avanzado.

Formación
Psicología Organizacional — Universidad San Ignacio de Loyola.

Habilidades
Reclutamiento, planillas, legislación laboral, Excel avanzado.
TXT],
        'jeanpierre.soto@correo.com' => ['archivo' => 'CV_JeanPierreSoto.pdf', 'texto' => <<<'TXT'
Jean Pierre Soto
Asistente Administrativo · Lima · jeanpierre.soto@correo.com

Perfil
Asistente administrativo con 2 años de experiencia de apoyo en nómina.

Experiencia
Asistente Administrativo — Consultora Contable Lima (2024 – actualidad)
Apoyo en el cálculo de la nómina y archivo de legajos.

Formación
Administración — Instituto Superior Tecnológico Lima.

Habilidades
Nómina, archivo, Excel.
TXT],
        'mariana.alarcon@correo.com' => ['archivo' => 'CV_MarianaAlarcon.docx', 'texto' => <<<'TXT'
Mariana Alarcón
Practicante de Recursos Humanos · Lima · mariana.alarcon@correo.com

Perfil
Estudiante de último año de Psicología con prácticas en selección de personal.

Experiencia
Practicante de RR. HH. — Retail Express (2025 – actualidad)
Apoyo en el reclutamiento: publicación de avisos, filtro de CV y coordinación de entrevistas.

Formación
Psicología — Universidad Femenina del Sagrado Corazón (último año).

Habilidades
Selección de personal, entrevistas, comunicación.
TXT],

        // — Nuna Salud · Contador General —
        'fernando.gutierrez@correo.com' => ['archivo' => 'CV_FernandoGutierrez.pdf', 'texto' => <<<'TXT'
Fernando Gutiérrez
Contador Público Colegiado · Lima · fernando.gutierrez@correo.com

Perfil
Contador con 8 años de experiencia en contabilidad general de empresas de servicios de salud.

Experiencia
Contador General — Grupo Médico Integral (2020 – actualidad)
Cierres contables mensuales y estados financieros bajo NIIF.
Declaraciones tributarias mensuales y anuales ante SUNAT.
Implementación del módulo SAP FI.

Contador — Estudio Contable Asociados (2018 – 2020)
Contabilidad general de diez clientes y conciliaciones con Excel avanzado.

Formación
Contabilidad — Universidad Nacional Mayor de San Marcos. Contador Público Colegiado.

Habilidades
Contabilidad, NIIF, tributación, SAP FI, Excel avanzado.
TXT],
        'silvia.paredes@correo.com' => ['archivo' => 'CV_SilviaParedes.docx', 'texto' => <<<'TXT'
Silvia Paredes
Contadora · Lima · silvia.paredes@correo.com

Perfil
Contadora con 5 años de experiencia en pequeñas empresas comerciales.

Experiencia
Contadora — Comercial Pacífico (2021 – actualidad)
Contabilidad general y declaraciones tributarias mensuales.
Conciliaciones bancarias con Excel avanzado.

Formación
Contabilidad — Universidad de San Martín de Porres.

Habilidades
Contabilidad, declaraciones tributarias, Excel avanzado.

Intereses
NIIF (nociones, curso en progreso).
TXT],
        'raul.inga@correo.com' => ['archivo' => 'CV_RaulInga.pdf', 'texto' => <<<'TXT'
Raúl Inga
Asistente Contable · Huancayo · raul.inga@correo.com

Perfil
Asistente contable con 3 años de experiencia en registro de compras y ventas.

Experiencia
Asistente Contable — Ferretería El Constructor (2023 – actualidad)
Registro de comprobantes y apoyo en la contabilidad general.

Formación
Contabilidad — Universidad Continental.

Habilidades
Contabilidad, registro de comprobantes, Excel.
TXT],

        // — Altura Café · Barista —
        'lucero.mamani@correo.com' => ['archivo' => 'CV_LuceroMamani.docx', 'texto' => <<<'TXT'
Lucero Mamani
Barista · Cusco · lucero.mamani@correo.com

Perfil
Barista con 3 años de experiencia en cafeterías de especialidad.

Experiencia
Barista — Café de la Plaza (2023 – actualidad)
Preparación de espresso y métodos filtrados con café peruano de especialidad.
Atención al cliente nacional y extranjero, en español e inglés básico.
Aplicación de buenas prácticas de manufactura (BPM) en la barra.

Formación
Curso de barismo profesional — Escuela de Café del Cusco.

Habilidades
Barismo, atención al cliente, manipulación de alimentos.
TXT],
        'thiago.huillca@correo.com' => ['archivo' => 'CV_ThiagoHuillca.pdf', 'texto' => <<<'TXT'
Thiago Huillca
Mozo · Cusco · thiago.huillca@correo.com

Perfil
Mozo con 2 años de experiencia en restaurantes turísticos.

Experiencia
Mozo — Restaurante Inti (2024 – actualidad)
Atención al cliente en salón y toma de pedidos.

Formación
Secundaria completa.

Habilidades
Atención al cliente, inglés básico.

Intereses
Barismo (interés en aprender).
TXT],
    ];
}

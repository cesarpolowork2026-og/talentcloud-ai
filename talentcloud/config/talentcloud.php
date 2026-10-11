<?php

return [

    /*
    | CVs de los candidatos (datos personales): disco privado y límites.
    */
    'cvs' => [
        'disco' => env('CV_DISK', 'cvs'),
        'max_kb' => (int) env('CV_MAX_KB', 5120),
        'mimes' => ['pdf', 'docx'],
    ],

    /*
    | Servicio de IA (talentcloud-ai, FastAPI). Deshabilitado, los CV se
    | procesan igual (texto extraído) y el matching usa el motor "fake".
    */
    'ia' => [
        'habilitada' => (bool) env('IA_HABILITADA', false),
        'url' => env('IA_URL', 'http://127.0.0.1:8001'),
        'api_key' => env('IA_API_KEY'),
        'timeout' => (int) env('IA_TIMEOUT', 30),
        'reintentos' => (int) env('IA_REINTENTOS', 2),
    ],

    /*
    | Motor de matching: "fake" (reglas y palabras clave, sin IA) o "http"
    | (servicio de IA, a partir del día 15 del plan).
    */
    'matching' => [
        'motor' => env('MATCHING_ENGINE', 'fake'),
    ],

    /*
    | Avisos al postulante sobre el estado de su postulación: se envían con
    | esta demora y solo si el estado sigue igual al enviarse (un arrastre
    | accidental en el Kanban, deshecho a tiempo, no llega a avisarse).
    */
    'avisos' => [
        'demora_minutos' => (int) env('AVISOS_DEMORA_MINUTOS', 10),
    ],

    /*
    | Responsable del tratamiento de datos (Ley 29733), para la política de
    | privacidad y los términos de uso. RUC y domicilio se muestran solo si
    | están definidos: completarlos antes de abrir el servicio al público.
    */
    'legal' => [
        'responsable' => env('LEGAL_RESPONSABLE', env('APP_NAME', 'TalentCloud AI')),
        // Vacío en el .env = el correo de soporte (env() devolvería '' y no el valor por defecto).
        'contacto' => env('LEGAL_CONTACTO') ?: (env('SOPORTE_CORREO') ?: 'talentcloudia@gmail.com'),
        'ruc' => env('LEGAL_RUC'),
        'domicilio' => env('LEGAL_DOMICILIO'),
        // Fecha de la última versión de los textos (cambiarla al editarlos).
        'actualizado' => '2026-10-09',
    ],

    /*
    | Proxies de confianza (balanceador o proxy de GCP, con HTTPS terminado
    | antes de llegar a la aplicación): IP o rangos separados por comas, o "*"
    | si solo el proxy alcanza la app. Se lee aquí, y no con env() al arrancar,
    | porque en producción la configuración va en caché (config:cache) y desde
    | entonces env() devuelve null fuera de config/.
    */
    'proxies' => env('TRUSTED_PROXIES'),

    /*
    | Correo de soporte: lo muestra el pie y recibe las hojas del Libro de
    | Reclamaciones. Un solo valor para no tener correos distintos por ahí.
    */
    'soporte' => [
        'correo' => env('SOPORTE_CORREO') ?: 'talentcloudia@gmail.com',
    ],

    /*
    | Libro de Reclamaciones (Código de Protección y Defensa del Consumidor,
    | Ley 29571, y su reglamento): plazo de respuesta en días hábiles y
    | feriados nacionales fijos (mes-día). Jueves y Viernes Santo se calculan
    | cada año (PlazoDeRespuesta). Revisar la lista si cambia la ley.
    */
    'reclamaciones' => [
        'dias_habiles' => 15,
        'feriados' => [
            '01-01', '05-01', '06-07', '06-29', '07-23', '07-28', '07-29',
            '08-06', '08-30', '10-08', '11-01', '12-08', '12-09', '12-25',
        ],
        // Hojas por IP y hora, y copias a un mismo correo por día (el formulario es público).
        'por_hora' => 5,
        'por_correo_al_dia' => 3,
    ],

];

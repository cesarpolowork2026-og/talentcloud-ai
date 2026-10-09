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
        'contacto' => env('LEGAL_CONTACTO', env('MAIL_FROM_ADDRESS')),
        'ruc' => env('LEGAL_RUC'),
        'domicilio' => env('LEGAL_DOMICILIO'),
        // Fecha de la última versión de los textos (cambiarla al editarlos).
        'actualizado' => '2026-10-09',
    ],

];

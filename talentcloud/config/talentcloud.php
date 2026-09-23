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

];

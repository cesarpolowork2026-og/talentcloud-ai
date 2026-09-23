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
    | Motor de matching: "fake" (reglas y palabras clave, sin IA) o "http"
    | (servicio de IA en FastAPI, a partir del día 15 del plan).
    */
    'matching' => [
        'motor' => env('MATCHING_ENGINE', 'fake'),
        'url' => env('MATCHING_URL', 'http://127.0.0.1:8001'),
        'timeout' => (int) env('MATCHING_TIMEOUT', 20),
    ],

];

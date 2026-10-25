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
    | Dashboard: las cifras agregadas se guardan en caché unos segundos por empresa.
    */
    'dashboard' => [
        // Segundos que se guardan las cifras agregadas (postulantes, embudo) por empresa; 0 = sin caché.
        'cache_segundos' => (int) env('DASHBOARD_CACHE_SEGUNDOS', 60),
    ],

    'demo' => [
        // Multiplica el volumen de postulaciones de VolumenDeDemostracionSeeder (1 = completo; 0.05 = prueba rápida).
        'escala_de_volumen' => (float) env('DEMO_ESCALA_DE_VOLUMEN', 1),
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
        'actualizado' => '2026-10-20',
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
    | Cobro en línea de los planes Starter y Pro (App\Pagos). Sin pasarela
    | (PAGOS_PASARELA vacío), «Mi empresa» solo permite solicitar el plan y lo
    | aplica el admin SaaS. Con "stripe", la empresa paga en Stripe Checkout y el
    | plan cambia solo cuando Stripe lo confirma por webhook. Precios e IGV:
    | identificadores creados en el panel de Stripe (en soles, sin IGV).
    */
    'pagos' => [
        'pasarela' => env('PAGOS_PASARELA') ?: null,
        'stripe' => [
            'clave_secreta' => env('STRIPE_SECRET'),
            'secreto_webhook' => env('STRIPE_WEBHOOK_SECRET'),
            'igv' => env('STRIPE_IGV') ?: null,
            'precios' => [
                'starter' => ['mensual' => env('STRIPE_PRECIO_STARTER_MENSUAL'), 'anual' => env('STRIPE_PRECIO_STARTER_ANUAL')],
                'pro' => ['mensual' => env('STRIPE_PRECIO_PRO_MENSUAL'), 'anual' => env('STRIPE_PRECIO_PRO_ANUAL')],
            ],
        ],
    ],

    /*
    | Correo de soporte: lo muestra el pie. Un solo valor para no tener correos
    | distintos por ahí.
    */
    'soporte' => [
        'correo' => env('SOPORTE_CORREO') ?: 'talentcloudia@gmail.com',
    ],

    /*
    | Dominios a los que nunca se envía correo: los de las cuentas ficticias de la
    | demostración. Algunos existen de verdad y el correo rebotaría o le llegaría a
    | un desconocido (y los rebotes dañan la reputación del dominio que envía).
    | Los avisos siguen apareciendo en la campana.
    */
    'correo' => [
        'dominios_sin_envio' => array_values(array_filter(array_map('trim', explode(',', (string) (env('CORREO_DOMINIOS_SIN_ENVIO')
            ?: 'correo.com,nimbus.com,vela.studio,rutasur.pe,nunasalud.pe,brioretail.pe,orbita.dev,alturacafe.pe,talentcloud.ai,example.com,example.org,example.net'))))),
    ],

];

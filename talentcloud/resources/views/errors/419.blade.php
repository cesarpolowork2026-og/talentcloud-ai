@include('errors.layout', [
    'titulo' => 'Página expirada',
    'mensaje' => 'Tu sesión venció: por inactividad o porque se inició sesión con otra cuenta en este mismo navegador. Vuelve a cargar la página e inténtalo de nuevo (para usar dos cuentas a la vez, abre una en una ventana de incógnito).',
    'icono' => 'clock',
    'colorIcono' => 'text-neutral-500',
])

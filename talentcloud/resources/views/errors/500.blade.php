@include('errors.layout', [
    'titulo' => 'Algo salió mal',
    'mensaje' => 'Ocurrió un error inesperado. Ya quedó registrado; inténtalo de nuevo en unos minutos.',
    'icono' => 'warning',
    'conNavegacion' => false,
    'colorIcono' => 'text-accent-2-600',
])

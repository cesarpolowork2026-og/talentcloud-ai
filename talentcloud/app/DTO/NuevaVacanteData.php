<?php

namespace App\DTO;

use App\Enums\Modalidad;

/** Formulario "Nueva vacante" del prototipo (título, empresa, competencias requeridas). */
final readonly class NuevaVacanteData
{
    /**
     * @param  list<string>  $competencias  Nombres de competencias ("Java", "Spring Boot"…).
     */
    public function __construct(
        public string $titulo,
        public ?string $ubicacion,
        public Modalidad $modalidad,
        public array $competencias,
    ) {}

    /**
     * La lista que envía el selector de competencias, sin vacíos ni repetidos (sin importar mayúsculas).
     *
     * @param  list<string>  $nombres
     * @return list<string>
     */
    public static function depurarCompetencias(array $nombres): array
    {
        $vistas = [];
        $resultado = [];

        foreach ($nombres as $parte) {
            $nombre = trim((string) preg_replace('/\s+/', ' ', $parte));
            $clave = mb_strtolower($nombre);

            if ($nombre !== '' && ! isset($vistas[$clave])) {
                $vistas[$clave] = true;
                $resultado[] = $nombre;
            }
        }

        return $resultado;
    }
}

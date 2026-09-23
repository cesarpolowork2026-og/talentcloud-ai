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
     * "Java, Spring Boot, PostgreSQL" → ['Java', 'Spring Boot', 'PostgreSQL'] (sin vacíos ni repetidos).
     *
     * @return list<string>
     */
    public static function separarCompetencias(string $texto): array
    {
        $vistas = [];
        $resultado = [];

        foreach (preg_split('/[,;\n]+/', $texto) ?: [] as $parte) {
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

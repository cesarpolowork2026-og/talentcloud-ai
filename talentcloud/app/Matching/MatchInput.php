<?php

namespace App\Matching;

/**
 * Contrato de entrada del matching. Es lo que Laravel
 * envía al motor: los requisitos de la vacante y el texto del CV. No lleva
 * datos personales que no hagan falta (ni nombre, ni correo, ni teléfono).
 */
final readonly class MatchInput
{
    public const VERSION = 1;

    /**
     * @param  list<RequisitoInput>  $requisitos
     */
    public function __construct(
        public int $postulacionId,
        public string $vacante,
        public ?string $descripcionVacante,
        public array $requisitos,
        public int $cvId,
        public string $textoCv,
    ) {}

    /**
     * Cuerpo JSON que recibe el servicio de IA (POST /matching/evaluar).
     *
     * @return array<string, mixed>
     */
    public function aArreglo(): array
    {
        return [
            'version' => self::VERSION,
            'postulacion_id' => $this->postulacionId,
            'vacante' => [
                'titulo' => $this->vacante,
                'descripcion' => $this->descripcionVacante,
            ],
            'requisitos' => array_map(fn (RequisitoInput $r) => $r->aArreglo(), $this->requisitos),
            'cv' => [
                'id' => $this->cvId,
                'texto' => $this->textoCv,
            ],
        ];
    }
}

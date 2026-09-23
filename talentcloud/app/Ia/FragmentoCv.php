<?php

namespace App\Ia;

/** Fragmento del CV con su embedding (sin encabezado ni datos personales). */
final readonly class FragmentoCv
{
    /**
     * @param  list<float>  $embedding
     */
    public function __construct(
        public string $seccion,
        public int $orden,
        public string $texto,
        public array $embedding,
    ) {}

    public static function desde(Campos $campos): self
    {
        return new self(
            seccion: $campos->texto('seccion'),
            orden: $campos->entero('orden'),
            texto: $campos->texto('texto'),
            embedding: $campos->vector('embedding'),
        );
    }
}

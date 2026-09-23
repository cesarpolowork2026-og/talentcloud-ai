<?php

namespace App\Ia;

/** Respuesta de POST /requisitos/embeddings (contrato versión 1). */
final readonly class EmbeddingsGenerados
{
    public const VERSION = 1;

    /**
     * @param  array<int, list<float>>  $vectores  id => vector
     */
    public function __construct(
        public string $modelo,
        public int $dimensiones,
        public array $vectores,
    ) {}

    /**
     * @param  array<mixed>  $respuesta
     */
    public static function desdeRespuesta(array $respuesta): self
    {
        $campos = new Campos($respuesta);

        if ($campos->entero('version') !== self::VERSION) {
            throw RespuestaIaNoValida::campo('version');
        }

        $vectores = [];
        foreach ($campos->objetos('embeddings') as $embedding) {
            $vectores[$embedding->entero('id')] = $embedding->vector('vector');
        }

        return new self($campos->texto('modelo'), $campos->entero('dimensiones'), $vectores);
    }

    /** @return list<float>|null */
    public function vectorDe(int $id): ?array
    {
        return $this->vectores[$id] ?? null;
    }
}

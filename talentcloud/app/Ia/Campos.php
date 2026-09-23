<?php

namespace App\Ia;

/**
 * Lectura tipada de una respuesta JSON del servicio de IA: cada método
 * devuelve el tipo esperado o lanza RespuestaIaNoValida con el campo que falla.
 */
final readonly class Campos
{
    /**
     * @param  array<mixed>  $datos
     */
    public function __construct(private array $datos, private string $ruta = '') {}

    public function entero(string $clave): int
    {
        $valor = $this->valor($clave);

        return is_int($valor) ? $valor : throw RespuestaIaNoValida::campo($this->nombre($clave));
    }

    public function texto(string $clave): string
    {
        $valor = $this->valor($clave);

        return is_string($valor) ? $valor : throw RespuestaIaNoValida::campo($this->nombre($clave));
    }

    public function textoOpcional(string $clave): ?string
    {
        return $this->valor($clave) === null ? null : $this->texto($clave);
    }

    public function decimalOpcional(string $clave): ?float
    {
        $valor = $this->valor($clave);

        return match (true) {
            $valor === null => null,
            is_int($valor), is_float($valor) => (float) $valor,
            default => throw RespuestaIaNoValida::campo($this->nombre($clave)),
        };
    }

    public function booleano(string $clave): bool
    {
        $valor = $this->valor($clave);

        return is_bool($valor) ? $valor : throw RespuestaIaNoValida::campo($this->nombre($clave));
    }

    /**
     * Vector de números no vacío.
     *
     * @return list<float>
     */
    public function vector(string $clave): array
    {
        $valor = $this->valor($clave);

        if (! is_array($valor) || $valor === [] || ! array_is_list($valor)) {
            throw RespuestaIaNoValida::campo($this->nombre($clave));
        }

        return array_map(
            fn (mixed $v) => is_int($v) || is_float($v) ? (float) $v : throw RespuestaIaNoValida::campo($this->nombre($clave)),
            $valor,
        );
    }

    /** @return array<mixed> */
    public function arreglo(string $clave): array
    {
        $valor = $this->valor($clave);

        return is_array($valor) ? $valor : throw RespuestaIaNoValida::campo($this->nombre($clave));
    }

    /**
     * Cada elemento de una lista de objetos, envuelto para seguir leyéndolo con tipos.
     *
     * @return list<self>
     */
    public function objetos(string $clave): array
    {
        $valor = $this->valor($clave);

        if (! is_array($valor) || ! array_is_list($valor)) {
            throw RespuestaIaNoValida::campo($this->nombre($clave));
        }

        return array_map(
            fn (mixed $elemento, int $i) => is_array($elemento)
                ? new self($elemento, $this->nombre($clave)."[{$i}].")
                : throw RespuestaIaNoValida::campo($this->nombre($clave)."[{$i}]"),
            $valor,
            array_keys($valor),
        );
    }

    private function valor(string $clave): mixed
    {
        return $this->datos[$clave] ?? null;
    }

    private function nombre(string $clave): string
    {
        return $this->ruta.$clave;
    }
}

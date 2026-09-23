<?php

namespace App\Ia;

/**
 * Puerto hacia el servicio de IA (talentcloud-ai). La implementación real es
 * HttpServicioIa; en los tests se simula la respuesta HTTP con Http::fake().
 */
interface ServicioIa
{
    /** Si está deshabilitado no se llama: los CV quedan con su texto y el matching usa el motor "fake". */
    public function habilitado(): bool;

    /** El servicio responde a /health (sin reintentos: es una comprobación rápida). */
    public function disponible(): bool;

    /**
     * Datos estructurados del CV y un embedding por fragmento.
     *
     * @param  list<array{id: int, nombre: string, sinonimos: list<string>}>  $catalogo
     *
     * @throws ServicioIaNoDisponible si no responde (conviene reintentar)
     * @throws RespuestaIaNoValida si rechaza la petición o responde fuera de contrato (no conviene reintentar)
     */
    public function analizarCv(int $cvId, string $texto, array $catalogo): AnalisisCv;

    /**
     * @param  array<int, string>  $textos  id => texto
     *
     * @throws ServicioIaNoDisponible
     * @throws RespuestaIaNoValida
     */
    public function generarEmbeddings(array $textos): EmbeddingsGenerados;
}

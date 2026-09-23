<?php

namespace App\Ia;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cliente HTTP del servicio de IA. Reintenta los fallos transitorios (conexión
 * y 5xx) y traduce los errores a excepciones del dominio.
 */
class HttpServicioIa implements ServicioIa
{
    private const VERSION_CONTRATO = 1;

    private const ESPERA_ENTRE_REINTENTOS_MS = 500;

    public function __construct(
        private readonly bool $habilitado,
        private readonly string $url,
        private readonly ?string $apiKey,
        private readonly int $timeout,
        private readonly int $reintentos,
    ) {}

    public function habilitado(): bool
    {
        return $this->habilitado;
    }

    public function disponible(): bool
    {
        try {
            return Http::baseUrl($this->url)->acceptJson()->timeout(5)->connectTimeout(3)->get('health')->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function analizarCv(int $cvId, string $texto, array $catalogo): AnalisisCv
    {
        return AnalisisCv::desdeRespuesta($this->post('cv/analizar', [
            'version' => self::VERSION_CONTRATO,
            'cv' => ['id' => $cvId, 'texto' => $texto],
            'catalogo' => $catalogo,
        ]));
    }

    public function generarEmbeddings(array $textos): EmbeddingsGenerados
    {
        return EmbeddingsGenerados::desdeRespuesta($this->post('requisitos/embeddings', [
            'version' => self::VERSION_CONTRATO,
            'textos' => array_map(
                fn (int $id, string $texto) => ['id' => $id, 'texto' => $texto],
                array_keys($textos),
                array_values($textos),
            ),
        ]));
    }

    /**
     * @param  array<string, mixed>  $cuerpo
     * @return array<mixed>
     */
    private function post(string $ruta, array $cuerpo): array
    {
        try {
            $respuesta = $this->cliente()->post($ruta, $cuerpo);
        } catch (ConnectionException $e) {
            throw ServicioIaNoDisponible::porConexion($e);
        }

        if ($respuesta->serverError()) {
            throw ServicioIaNoDisponible::porEstado($respuesta->status());
        }

        if ($respuesta->failed()) {
            throw RespuestaIaNoValida::porEstado($respuesta->status());
        }

        $json = $respuesta->json();

        return is_array($json) ? $json : throw RespuestaIaNoValida::campo('cuerpo');
    }

    private function cliente(): PendingRequest
    {
        return Http::baseUrl($this->url)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->connectTimeout(5)
            ->when($this->apiKey, fn (PendingRequest $http) => $http->withHeaders(['X-Api-Key' => $this->apiKey]))
            // Solo los fallos transitorios; un 4xx no mejora reintentando. Sin lanzar: post() decide.
            ->retry(
                $this->reintentos + 1,
                self::ESPERA_ENTRE_REINTENTOS_MS,
                fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            );
    }
}

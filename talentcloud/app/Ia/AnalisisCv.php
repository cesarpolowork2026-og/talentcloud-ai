<?php

namespace App\Ia;

/** Respuesta de POST /cv/analizar (contrato versión 1). */
final readonly class AnalisisCv
{
    public const VERSION = 1;

    /**
     * @param  list<CompetenciaExtraida>  $competencias
     * @param  list<FragmentoCv>  $fragmentos
     * @param  list<array<mixed>>  $secciones
     * @param  list<array<mixed>>  $cargos
     * @param  list<array<mixed>>  $certificaciones
     */
    public function __construct(
        public array $competencias,
        public array $fragmentos,
        public string $modeloEmbeddings,
        public ?float $experienciaTotalAnios,
        public array $secciones,
        public array $cargos,
        public array $certificaciones,
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

        return new self(
            competencias: array_map(CompetenciaExtraida::desde(...), $campos->objetos('competencias')),
            fragmentos: array_map(FragmentoCv::desde(...), $campos->objetos('fragmentos')),
            modeloEmbeddings: $campos->texto('modelo_embeddings'),
            experienciaTotalAnios: $campos->decimalOpcional('experiencia_total_anios'),
            secciones: self::lista($campos->arreglo('secciones')),
            cargos: self::lista($campos->arreglo('cargos')),
            certificaciones: self::lista($campos->arreglo('certificaciones')),
        );
    }

    /**
     * Solo las que acreditan la competencia: una mención como "interés en aprender" no cuenta.
     *
     * @return list<CompetenciaExtraida>
     */
    public function competenciasAcreditadas(): array
    {
        return array_values(array_filter($this->competencias, fn (CompetenciaExtraida $c) => ! $c->dudosa));
    }

    /**
     * Lo que se guarda en cvs.datos_estructurados. Sin el texto de cada sección ni los
     * embeddings (el texto ya está en el CV y los vectores, en cv_fragmentos).
     *
     * @return array<string, mixed>
     */
    public function datosEstructurados(): array
    {
        return [
            'version' => self::VERSION,
            'secciones' => array_map(fn (array $s) => array_intersect_key($s, array_flip(['nombre', 'titulo', 'orden'])), $this->secciones),
            'competencias' => array_map(fn (CompetenciaExtraida $c) => $c->aArreglo(), $this->competencias),
            'experiencia_total_anios' => $this->experienciaTotalAnios,
            'cargos' => $this->cargos,
            'certificaciones' => $this->certificaciones,
            'modelo_embeddings' => $this->modeloEmbeddings,
        ];
    }

    /**
     * @param  array<mixed>  $valor
     * @return list<array<mixed>>
     */
    private static function lista(array $valor): array
    {
        return array_values(array_filter($valor, is_array(...)));
    }
}

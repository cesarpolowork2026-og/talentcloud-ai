"""Contrato del análisis de CV (POST /cv/analizar, versión 1).

Laravel envía el texto ya extraído y el catálogo de competencias (es la fuente de verdad);
el servicio devuelve los datos estructurados, siempre con el fragmento de origen como evidencia.
"""

from typing import Annotated, Literal

from pydantic import Field

from talentcloud_ai.contrato.comun import MAX_NOMBRE, CvInput, Entrada, Id, Nombre, Salida, SeccionCv

VERSION: Literal[1] = 1


class CompetenciaCatalogo(Entrada):
    id: Id
    nombre: Nombre
    sinonimos: Annotated[list[Annotated[str, Field(max_length=MAX_NOMBRE)]], Field(max_length=50, default_factory=list)]


class AnalisisCvInput(Entrada):
    version: Literal[1]
    cv: CvInput
    catalogo: Annotated[list[CompetenciaCatalogo], Field(max_length=5_000)]


class Seccion(Salida):
    nombre: SeccionCv
    titulo: str | None
    orden: int
    texto: str


class CompetenciaDetectada(Salida):
    competencia_id: int
    nombre: str
    anios: float | None
    evidencia: str
    seccion: SeccionCv
    # Solo mencionada como interés o nivel básico ("Kubernetes (interés en aprender)").
    dudosa: bool


class Cargo(Salida):
    cargo: str
    organizacion: str | None
    desde: int
    hasta: int | None  # None: sigue en el puesto
    evidencia: str


class Certificacion(Salida):
    nombre: str
    evidencia: str


class Fragmento(Salida):
    """Unidad de texto con su embedding. Nunca incluye el encabezado ni los datos personales."""

    seccion: SeccionCv
    orden: int
    texto: str
    embedding: list[float] | None = None


class AnalisisCv(Salida):
    version: Literal[1] = VERSION
    secciones: list[Seccion]
    competencias: list[CompetenciaDetectada]
    experiencia_total_anios: float | None
    cargos: list[Cargo]
    certificaciones: list[Certificacion]
    fragmentos: list[Fragmento]
    # Modelo que generó los embeddings de los fragmentos (para reindexar si cambia).
    modelo_embeddings: str | None = None

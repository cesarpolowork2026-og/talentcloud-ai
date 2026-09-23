"""Contrato de embeddings de requisitos (POST /requisitos/embeddings, versión 1)."""

from typing import Annotated, Literal

from pydantic import Field

from talentcloud_ai.contrato.comun import Entrada, Id, Salida

VERSION: Literal[1] = 1


class TextoParaEmbedding(Entrada):
    id: Id
    texto: Annotated[str, Field(min_length=1, max_length=2_000)]


class EmbeddingsInput(Entrada):
    version: Literal[1]
    textos: Annotated[list[TextoParaEmbedding], Field(min_length=1, max_length=500)]


class Embedding(Salida):
    id: int
    vector: list[float]


class EmbeddingsResultado(Salida):
    version: Literal[1] = VERSION
    modelo: str
    dimensiones: int
    embeddings: list[Embedding]

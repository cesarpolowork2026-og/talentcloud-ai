"""Casos de uso del servicio: combinan la extracción (sin estado ni modelo) con el embedder cargado."""

from datetime import date

from talentcloud_ai.contrato.cv import AnalisisCv, AnalisisCvInput
from talentcloud_ai.contrato.embeddings import Embedding, EmbeddingsInput, EmbeddingsResultado
from talentcloud_ai.embeddings.base import Embedder
from talentcloud_ai.extraccion.analizador import AnalizadorDeCv


def analizar_cv(entrada: AnalisisCvInput, embedder: Embedder, hoy: date) -> AnalisisCv:
    """Datos estructurados del CV y un embedding por fragmento (en un solo lote)."""
    analisis = AnalizadorDeCv(entrada.catalogo, hoy).analizar(entrada.cv.texto)
    vectores = embedder.codificar([f.texto for f in analisis.fragmentos])

    return analisis.model_copy(
        update={
            "fragmentos": [
                f.model_copy(update={"embedding": v}) for f, v in zip(analisis.fragmentos, vectores, strict=True)
            ],
            "modelo_embeddings": embedder.modelo,
        }
    )


def generar_embeddings(entrada: EmbeddingsInput, embedder: Embedder) -> EmbeddingsResultado:
    vectores = embedder.codificar([t.texto for t in entrada.textos])
    return EmbeddingsResultado(
        modelo=embedder.modelo,
        dimensiones=embedder.dimensiones,
        embeddings=[Embedding(id=t.id, vector=v) for t, v in zip(entrada.textos, vectores, strict=True)],
    )

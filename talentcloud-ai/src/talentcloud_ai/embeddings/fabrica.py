"""Construye el embedder configurado. Se crea una sola vez, al iniciar el servicio."""

from talentcloud_ai.config import Settings
from talentcloud_ai.embeddings.base import Embedder
from talentcloud_ai.embeddings.determinista import EmbedderDeterminista


class BackendNoDisponibleError(RuntimeError):
    """El backend de embeddings configurado no está instalado en este entorno."""


def crear_embedder(settings: Settings) -> Embedder:
    if settings.embeddings_backend == "determinista":
        return EmbedderDeterminista(settings.dimensiones_embeddings)
    # sentence-transformers llega el día 13, con su propio grupo de dependencias.
    raise BackendNoDisponibleError(f"El backend de embeddings '{settings.embeddings_backend}' aún no está disponible.")

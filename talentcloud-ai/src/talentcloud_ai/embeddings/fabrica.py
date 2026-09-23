"""Construye el embedder configurado. Se crea una sola vez, al iniciar el servicio."""

from talentcloud_ai.config import Settings
from talentcloud_ai.embeddings.base import Embedder
from talentcloud_ai.embeddings.determinista import EmbedderDeterminista


class BackendNoDisponibleError(RuntimeError):
    """El backend de embeddings configurado no está instalado en este entorno."""


class DimensionesIncompatiblesError(RuntimeError):
    """El modelo no produce vectores del tamaño que espera la base de datos (vector(384) en Laravel)."""


def crear_embedder(settings: Settings) -> Embedder:
    embedder = _construir(settings)
    # Falla al iniciar, no al guardar: un vector de otro tamaño no cabe en la columna de pgvector.
    if embedder.dimensiones != settings.dimensiones_embeddings:
        raise DimensionesIncompatiblesError(
            f"El modelo {embedder.modelo} produce {embedder.dimensiones} dimensiones; "
            f"se esperaban {settings.dimensiones_embeddings}."
        )
    return embedder


def _construir(settings: Settings) -> Embedder:
    if settings.embeddings_backend == "determinista":
        return EmbedderDeterminista(settings.dimensiones_embeddings)

    try:
        # Importación diferida: solo existe con el extra "ml".
        from talentcloud_ai.embeddings.sentence_transformers import EmbedderSentenceTransformers  # noqa: PLC0415
    except ImportError as error:
        raise BackendNoDisponibleError(
            "sentence-transformers no está instalado: ejecuta `uv sync --extra ml`."
        ) from error

    return EmbedderSentenceTransformers(settings.modelo_embeddings)

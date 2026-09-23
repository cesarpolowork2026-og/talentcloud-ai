"""Embedder con sentence-transformers (paraphrase-multilingual-MiniLM-L12-v2: español e inglés, 384 dimensiones).

Requiere el extra "ml". El modelo se carga una vez, al crear el embedder (al iniciar el servicio).
"""

from collections.abc import Sequence

from sentence_transformers import SentenceTransformer

_TAMANO_DE_LOTE = 32


class EmbedderSentenceTransformers:
    def __init__(self, modelo: str, dispositivo: str = "cpu") -> None:
        self._nombre = modelo
        self._modelo = SentenceTransformer(modelo, device=dispositivo)
        # sentence-transformers 6 renombró el método; se aceptan ambas versiones.
        obtener_dimension = getattr(self._modelo, "get_embedding_dimension", None) or (
            self._modelo.get_sentence_embedding_dimension
        )
        dimensiones = obtener_dimension()
        if dimensiones is None:
            raise ValueError(f"El modelo {modelo} no informa la dimensión de sus embeddings.")
        self._dimensiones = int(dimensiones)

    @property
    def modelo(self) -> str:
        return self._nombre

    @property
    def dimensiones(self) -> int:
        return self._dimensiones

    def codificar(self, textos: Sequence[str]) -> list[list[float]]:
        if not textos:
            return []
        vectores = self._modelo.encode(
            list(textos),
            batch_size=_TAMANO_DE_LOTE,
            normalize_embeddings=True,  # norma 1: la similitud coseno es el producto escalar
            convert_to_numpy=True,
            show_progress_bar=False,
        )
        return [[float(v) for v in vector] for vector in vectores]

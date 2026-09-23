"""Interfaz de los generadores de embeddings."""

from collections.abc import Sequence
from typing import Protocol


class Embedder(Protocol):
    """Convierte textos en vectores normalizados (norma 1) de `dimensiones` componentes."""

    @property
    def modelo(self) -> str: ...

    @property
    def dimensiones(self) -> int: ...

    def codificar(self, textos: Sequence[str]) -> list[list[float]]: ...

"""Embedder sin modelo (hashing de palabras): determinista y sin descargas.

Sirve para los tests y para desarrollar sin los pesos del modelo; no capta sinónimos ni significado.
En producción se usa sentence-transformers (día 13).
"""

import hashlib
import math
import re
from collections.abc import Sequence

from talentcloud_ai.extraccion.texto import normalizar

_PALABRA = re.compile(r"[a-z0-9]+")


class EmbedderDeterminista:
    def __init__(self, dimensiones: int = 384) -> None:
        if dimensiones < 1:
            raise ValueError("Las dimensiones deben ser positivas.")
        self._dimensiones = dimensiones

    @property
    def modelo(self) -> str:
        return "determinista-hashing"

    @property
    def dimensiones(self) -> int:
        return self._dimensiones

    def codificar(self, textos: Sequence[str]) -> list[list[float]]:
        return [self._vector(texto) for texto in textos]

    def _vector(self, texto: str) -> list[float]:
        vector = [0.0] * self._dimensiones
        for palabra in _PALABRA.findall(normalizar(texto)):
            resumen = hashlib.blake2b(palabra.encode(), digest_size=8).digest()
            indice = int.from_bytes(resumen[:4], "big") % self._dimensiones
            vector[indice] += 1.0 if resumen[4] % 2 == 0 else -1.0
        norma = math.sqrt(sum(v * v for v in vector))
        return [v / norma for v in vector] if norma else vector

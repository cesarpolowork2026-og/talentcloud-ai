"""Métricas de evaluación: precisión y recall de la extracción y acierto del nivel por requisito."""

from collections import Counter
from collections.abc import Iterable, Mapping
from dataclasses import dataclass

from talentcloud_ai.contrato.comun import NivelCoincidencia


@dataclass(frozen=True)
class PrecisionRecall:
    verdaderos: int
    falsos_positivos: int
    falsos_negativos: int

    @property
    def precision(self) -> float:
        predichos = self.verdaderos + self.falsos_positivos
        return self.verdaderos / predichos if predichos else 1.0

    @property
    def recall(self) -> float:
        reales = self.verdaderos + self.falsos_negativos
        return self.verdaderos / reales if reales else 1.0

    @property
    def f1(self) -> float:
        suma = self.precision + self.recall
        return 2 * self.precision * self.recall / suma if suma else 0.0


def extraccion(esperadas: Mapping[str, set[str]], obtenidas: Mapping[str, set[str]]) -> PrecisionRecall:
    """Micro-promedio sobre todos los CV: cada competencia de cada CV es un caso."""
    verdaderos = falsos_positivos = falsos_negativos = 0
    for cv, esperado in esperadas.items():
        obtenido = obtenidas.get(cv, set())
        verdaderos += len(esperado & obtenido)
        falsos_positivos += len(obtenido - esperado)
        falsos_negativos += len(esperado - obtenido)
    return PrecisionRecall(verdaderos, falsos_positivos, falsos_negativos)


@dataclass(frozen=True)
class Niveles:
    """Acierto del nivel por requisito."""

    pares: tuple[tuple[NivelCoincidencia, NivelCoincidencia], ...]  # (esperado, predicho)

    @property
    def aciertos(self) -> int:
        return sum(e is p for e, p in self.pares)

    @property
    def total(self) -> int:
        return len(self.pares)

    @property
    def exactitud(self) -> float:
        return self.aciertos / self.total if self.total else 0.0

    @property
    def falsos_coincide(self) -> int:
        """Dar por acreditada una competencia que el CV no tiene: el error más costoso para el reclutador."""
        return sum(e is NivelCoincidencia.NO_ENCONTRADO and p is NivelCoincidencia.COINCIDE for e, p in self.pares)

    def por_nivel(self, nivel: NivelCoincidencia) -> PrecisionRecall:
        return PrecisionRecall(
            verdaderos=sum(e is nivel and p is nivel for e, p in self.pares),
            falsos_positivos=sum(e is not nivel and p is nivel for e, p in self.pares),
            falsos_negativos=sum(e is nivel and p is not nivel for e, p in self.pares),
        )

    def matriz_de_confusion(self) -> dict[str, dict[str, int]]:
        conteo = Counter(self.pares)
        return {e.value: {p.value: conteo[(e, p)] for p in NivelCoincidencia} for e in NivelCoincidencia}


def niveles(pares: Iterable[tuple[NivelCoincidencia, NivelCoincidencia]]) -> Niveles:
    return Niveles(tuple(pares))

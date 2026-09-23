"""Calibración de los umbrales semánticos sobre un conjunto de casos etiquetados.

Cada caso guarda su diagnóstico (evidencia exacta y semántica, calculadas una vez con el modelo); probar
otros umbrales solo vuelve a calificar, con la misma función que usa el servicio en producción.
"""

from collections import Counter
from collections.abc import Iterator, Sequence
from dataclasses import dataclass

from talentcloud_ai.contrato.comun import NivelCoincidencia
from talentcloud_ai.matching.calificacion import UmbralesSemanticos, calificar
from talentcloud_ai.matching.evaluador import Diagnostico

# Sin evidencia semántica: equivale a la coincidencia exacta sola (línea base).
SOLO_EXACTA = UmbralesSemanticos(revisar=1.0, coincide=1.0)


@dataclass(frozen=True)
class Caso:
    grupo: str  # el CV: la validación cruzada deja fuera un CV entero
    diagnostico: Diagnostico
    esperado: NivelCoincidencia

    def predecir(self, umbrales: UmbralesSemanticos) -> NivelCoincidencia:
        d = self.diagnostico
        return calificar(d.requisito, d.exacta, d.semantica, umbrales).nivel


@dataclass(frozen=True)
class Medicion:
    umbrales: UmbralesSemanticos
    aciertos: int
    total: int
    # Lo más costoso para el reclutador: dar por buena una competencia que el CV no tiene.
    falsos_coincide: int

    @property
    def exactitud(self) -> float:
        return self.aciertos / self.total if self.total else 0.0


def medir(casos: Sequence[Caso], umbrales: UmbralesSemanticos) -> Medicion:
    predichos = [(c.predecir(umbrales), c.esperado) for c in casos]
    return Medicion(
        umbrales=umbrales,
        aciertos=sum(p is e for p, e in predichos),
        total=len(predichos),
        falsos_coincide=sum(
            p is NivelCoincidencia.COINCIDE and e is NivelCoincidencia.NO_ENCONTRADO for p, e in predichos
        ),
    )


def _candidatos(desde: float, hasta: float, paso: float) -> Iterator[UmbralesSemanticos]:
    pasos = round((hasta - desde) / paso)
    valores = [round(desde + i * paso, 4) for i in range(pasos + 1)]
    for revisar in valores:
        for coincide in valores:
            if coincide >= revisar:
                yield UmbralesSemanticos(revisar=revisar, coincide=coincide)


def buscar_umbrales(casos: Sequence[Caso], desde: float = 0.2, hasta: float = 0.9, paso: float = 0.01) -> Medicion:
    """Los umbrales con más aciertos; a igualdad, menos falsos "Coincide" y umbrales más altos (prudentes)."""
    return max(
        (medir(casos, u) for u in _candidatos(desde, hasta, paso)),
        key=lambda m: (m.aciertos, -m.falsos_coincide, m.umbrales.revisar, m.umbrales.coincide),
    )


def validacion_cruzada(casos: Sequence[Caso], **busqueda: float) -> float:
    """Exactitud dejando fuera un grupo (CV) cada vez: calibra con el resto y mide en el excluido."""
    grupos = sorted({c.grupo for c in casos})
    aciertos = 0
    for grupo in grupos:
        entrenamiento = [c for c in casos if c.grupo != grupo]
        prueba = [c for c in casos if c.grupo == grupo]
        aciertos += medir(prueba, buscar_umbrales(entrenamiento, **busqueda).umbrales).aciertos
    return aciertos / len(casos) if casos else 0.0


def matriz_de_confusion(casos: Sequence[Caso], umbrales: UmbralesSemanticos) -> dict[str, dict[str, int]]:
    """esperado → predicho → cantidad."""
    conteo = Counter((c.esperado, c.predecir(umbrales)) for c in casos)
    return {e.value: {p.value: conteo[(e, p)] for p in NivelCoincidencia} for e in NivelCoincidencia}

"""Calificación de un requisito combinando la evidencia exacta y la semántica.

Orden de confianza: una mención literal de la competencia (o un sinónimo) manda, porque además permite
leer los años y distinguir menciones dudosas ("interés en aprender"). Solo cuando no la hay se usa la
similitud semántica, que nunca llega a los porcentajes de una coincidencia exacta.
"""

from dataclasses import dataclass
from enum import Enum, auto

from talentcloud_ai.contrato.comun import NivelCoincidencia, SeccionCv
from talentcloud_ai.contrato.cv import CompetenciaDetectada
from talentcloud_ai.contrato.matching import RequisitoInput
from talentcloud_ai.matching import umbrales
from talentcloud_ai.matching.semantica import EvidenciaSemantica

_EVIDENCIA_LABORAL = frozenset({SeccionCv.EXPERIENCIA, SeccionCv.PROYECTOS})


class Razon(Enum):
    CUMPLE_ANIOS = auto()
    COINCIDENCIA_EXACTA = auto()
    ANIOS_INSUFICIENTES = auto()
    ANIOS_NO_INDICADOS = auto()
    SOLO_INTERES = auto()
    SIMILITUD_ALTA = auto()
    SIMILITUD_PARCIAL = auto()
    SIN_EVIDENCIA = auto()


@dataclass(frozen=True)
class UmbralesSemanticos:
    """Similitud mínima para "Revisar" y para "Coincide" sin mención literal (calibradas en evaluacion/)."""

    revisar: float = umbrales.SIMILITUD_REVISAR
    coincide: float = umbrales.SIMILITUD_COINCIDE

    def __post_init__(self) -> None:
        if not 0 <= self.revisar <= self.coincide <= 1:
            raise ValueError("Se espera 0 ≤ revisar ≤ coincide ≤ 1.")


CALIBRADOS = UmbralesSemanticos()


@dataclass(frozen=True)
class Calificacion:
    porcentaje: int
    razon: Razon
    evidencia: str | None = None
    seccion: SeccionCv | None = None
    similitud: float | None = None

    @property
    def nivel(self) -> NivelCoincidencia:
        return nivel_de(self.porcentaje)


def nivel_de(porcentaje: int) -> NivelCoincidencia:
    if porcentaje >= umbrales.UMBRAL_COINCIDE:
        return NivelCoincidencia.COINCIDE
    return NivelCoincidencia.REVISAR if porcentaje > 0 else NivelCoincidencia.NO_ENCONTRADO


def calificar(
    requisito: RequisitoInput,
    exacta: CompetenciaDetectada | None,
    semantica: EvidenciaSemantica | None = None,
    umbrales_semanticos: UmbralesSemanticos = CALIBRADOS,
) -> Calificacion:
    """Porcentaje de un requisito, la razón que lo explica y su evidencia."""
    if exacta is not None:
        return _por_evidencia_exacta(requisito, exacta)
    if semantica is not None:
        return _por_similitud(semantica, umbrales_semanticos)
    return Calificacion(0, Razon.SIN_EVIDENCIA)


def _por_evidencia_exacta(requisito: RequisitoInput, exacta: CompetenciaDetectada) -> Calificacion:
    def con(porcentaje: int, razon: Razon) -> Calificacion:
        return Calificacion(porcentaje, razon, exacta.evidencia, exacta.seccion)

    if exacta.dudosa:
        return con(umbrales.SOLO_INTERES, Razon.SOLO_INTERES)

    anios = exacta.anios
    minimo = requisito.anios_minimos

    if minimo:
        if anios is None:
            return con(umbrales.ANIOS_NO_INDICADOS, Razon.ANIOS_NO_INDICADOS)
        if anios >= minimo:
            extra = round((anios - minimo) * umbrales.BONO_POR_ANIO_EXTRA)
            return con(min(umbrales.MAXIMO, umbrales.CUMPLE_MINIMO + extra), Razon.CUMPLE_ANIOS)
        porcentaje = umbrales.BASE_ANIOS_INSUFICIENTES + round(umbrales.RANGO_ANIOS_INSUFICIENTES * anios / minimo)
        return con(porcentaje, Razon.ANIOS_INSUFICIENTES)

    porcentaje = umbrales.BASE_MENCION
    if exacta.seccion in _EVIDENCIA_LABORAL:
        porcentaje += umbrales.BONO_EXPERIENCIA_LABORAL
    if anios is not None:
        porcentaje += min(umbrales.MAX_BONO_ANIOS, round(anios * umbrales.BONO_POR_ANIO))
    return con(min(umbrales.MAXIMO, porcentaje), Razon.COINCIDENCIA_EXACTA)


def _por_similitud(semantica: EvidenciaSemantica, limites: UmbralesSemanticos) -> Calificacion:
    similitud = semantica.similitud

    # Un interés o nivel básico nunca acredita la competencia, por mucho que se parezca.
    if similitud >= limites.coincide and not semantica.dudosa:
        porcentaje = _interpolar(
            similitud, limites.coincide, 1.0, umbrales.SEMANTICA_COINCIDE_MIN, umbrales.SEMANTICA_COINCIDE_MAX
        )
        razon = Razon.SIMILITUD_ALTA
    elif similitud >= limites.revisar:
        porcentaje = _interpolar(
            similitud, limites.revisar, limites.coincide, umbrales.SEMANTICA_REVISAR_MIN, umbrales.SEMANTICA_REVISAR_MAX
        )
        razon = Razon.SIMILITUD_PARCIAL
    else:
        return Calificacion(0, Razon.SIN_EVIDENCIA)

    return Calificacion(porcentaje, razon, semantica.texto, semantica.seccion, semantica.similitud)


def _interpolar(valor: float, desde: float, hasta: float, minimo: int, maximo: int) -> int:
    """Lleva `valor` de [desde, hasta] a [minimo, maximo], acotado."""
    proporcion = 0.0 if hasta <= desde else min(1.0, max(0.0, (valor - desde) / (hasta - desde)))
    return round(minimo + (maximo - minimo) * proporcion)

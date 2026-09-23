"""Evaluación de una postulación por evidencia exacta: competencia (o sinónimo) y años acreditados en el CV.

Es la base del matching; el día 14 se suma la similitud semántica de los embeddings. Sugiere y explica:
no decide ni mueve la postulación.
"""

from dataclasses import dataclass
from datetime import date

from talentcloud_ai.contrato.comun import NivelCoincidencia, SeccionCv
from talentcloud_ai.contrato.cv import CompetenciaCatalogo, CompetenciaDetectada
from talentcloud_ai.contrato.matching import MatchInput, MatchResult, RequisitoInput, RequisitoMatch
from talentcloud_ai.explicabilidad.motivos import Razon, motivo
from talentcloud_ai.explicabilidad.resumen import resumir
from talentcloud_ai.extraccion.analizador import AnalizadorDeCv
from talentcloud_ai.matching import umbrales

_EVIDENCIA_LABORAL = frozenset({SeccionCv.EXPERIENCIA, SeccionCv.PROYECTOS})


@dataclass(frozen=True)
class Calificacion:
    porcentaje: int
    razon: Razon


def calificar(requisito: RequisitoInput, detectada: CompetenciaDetectada | None) -> Calificacion:
    """Porcentaje de un requisito y la razón que lo explica."""
    if detectada is None:
        return Calificacion(0, Razon.SIN_EVIDENCIA)
    if detectada.dudosa:
        return Calificacion(umbrales.SOLO_INTERES, Razon.SOLO_INTERES)

    anios = detectada.anios
    minimo = requisito.anios_minimos

    if minimo:
        if anios is None:
            return Calificacion(umbrales.ANIOS_NO_INDICADOS, Razon.ANIOS_NO_INDICADOS)
        if anios >= minimo:
            extra = round((anios - minimo) * umbrales.BONO_POR_ANIO_EXTRA)
            return Calificacion(min(umbrales.MAXIMO, umbrales.CUMPLE_MINIMO + extra), Razon.CUMPLE_ANIOS)
        proporcion = anios / minimo
        porcentaje = umbrales.BASE_ANIOS_INSUFICIENTES + round(umbrales.RANGO_ANIOS_INSUFICIENTES * proporcion)
        return Calificacion(porcentaje, Razon.ANIOS_INSUFICIENTES)

    porcentaje = umbrales.BASE_MENCION
    if detectada.seccion in _EVIDENCIA_LABORAL:
        porcentaje += umbrales.BONO_EXPERIENCIA_LABORAL
    if anios is not None:
        porcentaje += min(umbrales.MAX_BONO_ANIOS, round(anios * umbrales.BONO_POR_ANIO))
    return Calificacion(min(umbrales.MAXIMO, porcentaje), Razon.COINCIDENCIA_EXACTA)


def nivel_de(porcentaje: int) -> NivelCoincidencia:
    if porcentaje >= umbrales.UMBRAL_COINCIDE:
        return NivelCoincidencia.COINCIDE
    return NivelCoincidencia.REVISAR if porcentaje > 0 else NivelCoincidencia.NO_ENCONTRADO


def puntaje_global(requisitos: list[RequisitoInput], resultados: list[RequisitoMatch]) -> float:
    """Promedio de los porcentajes ponderado por el peso de cada requisito."""
    peso_total = sum(r.peso for r in requisitos)
    if peso_total == 0:
        return 0.0
    suma = sum(r.peso * m.porcentaje for r, m in zip(requisitos, resultados, strict=True))
    return round(suma / peso_total, 2)


class EvaluadorDeMatching:
    def __init__(self, hoy: date) -> None:
        self._hoy = hoy

    def evaluar(self, entrada: MatchInput) -> MatchResult:
        # Cada requisito se busca como una competencia de catálogo, con sus sinónimos.
        catalogo = [
            CompetenciaCatalogo(id=r.id, nombre=r.competencia, sinonimos=r.sinonimos) for r in entrada.requisitos
        ]
        analizador = AnalizadorDeCv(catalogo, self._hoy)
        detectadas = {d.competencia_id: d for d in analizador.consolidar(analizador.menciones(entrada.cv.texto))}

        resultados: list[RequisitoMatch] = []
        for requisito in entrada.requisitos:
            detectada = detectadas.get(requisito.id)
            calificacion = calificar(requisito, detectada)
            resultados.append(
                RequisitoMatch(
                    requisito_id=requisito.id,
                    competencia=requisito.competencia,
                    porcentaje=calificacion.porcentaje,
                    nivel=nivel_de(calificacion.porcentaje),
                    evidencia=detectada.evidencia if detectada else None,
                    motivo=motivo(calificacion.razon, requisito, detectada),
                )
            )

        return MatchResult(
            score=puntaje_global(entrada.requisitos, resultados),
            resumen=resumir(entrada.requisitos, resultados),
            requisitos=resultados,
        )

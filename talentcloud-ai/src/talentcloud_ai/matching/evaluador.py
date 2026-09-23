"""Evaluación de una postulación, requisito por requisito, con evidencia y motivo.

Combina la coincidencia exacta (competencia o sinónimo, años acreditados, menciones dudosas) con la
similitud semántica del fragmento más parecido cuando el CV no nombra la competencia. Sugiere y
explica: no decide ni mueve la postulación.
"""

from collections.abc import Sequence
from dataclasses import dataclass
from datetime import date

from talentcloud_ai.contrato.cv import CompetenciaCatalogo, CompetenciaDetectada
from talentcloud_ai.contrato.matching import MatchInput, MatchResult, RequisitoInput, RequisitoMatch
from talentcloud_ai.embeddings.base import Embedder
from talentcloud_ai.explicabilidad.motivos import motivo
from talentcloud_ai.explicabilidad.resumen import resumir
from talentcloud_ai.extraccion import fragmentos
from talentcloud_ai.extraccion.analizador import AnalizadorDeCv
from talentcloud_ai.extraccion.secciones import BloqueDeSeccion, segmentar
from talentcloud_ai.matching.calificacion import CALIBRADOS, Calificacion, UmbralesSemanticos, calificar
from talentcloud_ai.matching.semantica import BuscadorSemantico, EvidenciaSemantica


def puntaje_global(requisitos: Sequence[RequisitoInput], resultados: Sequence[RequisitoMatch]) -> float:
    """Promedio de los porcentajes ponderado por el peso de cada requisito."""
    peso_total = sum(r.peso for r in requisitos)
    if peso_total == 0:
        return 0.0
    suma = sum(r.peso * m.porcentaje for r, m in zip(requisitos, resultados, strict=True))
    return round(suma / peso_total, 2)


@dataclass(frozen=True)
class Diagnostico:
    """Todo lo que se sabe de un requisito: evidencia exacta, evidencia semántica y la calificación resultante."""

    requisito: RequisitoInput
    exacta: CompetenciaDetectada | None
    semantica: EvidenciaSemantica | None
    calificacion: Calificacion


class EvaluadorDeMatching:
    def __init__(self, hoy: date, embedder: Embedder) -> None:
        self._hoy = hoy
        self._embedder = embedder

    def evaluar(self, entrada: MatchInput) -> MatchResult:
        resultados = [
            RequisitoMatch(
                requisito_id=d.requisito.id,
                competencia=d.requisito.competencia,
                porcentaje=d.calificacion.porcentaje,
                nivel=d.calificacion.nivel,
                evidencia=d.calificacion.evidencia,
                motivo=motivo(d.calificacion, d.requisito, d.exacta),
            )
            for d in self.diagnosticar(entrada)
        ]

        return MatchResult(
            score=puntaje_global(entrada.requisitos, resultados),
            resumen=resumir(entrada.requisitos, resultados),
            requisitos=resultados,
        )

    def diagnosticar(
        self, entrada: MatchInput, umbrales_semanticos: UmbralesSemanticos = CALIBRADOS
    ) -> list[Diagnostico]:
        """Evidencia y calificación de cada requisito (la calibración prueba otros umbrales con esto)."""
        bloques = segmentar(entrada.cv.texto)
        exactas = self._evidencia_exacta(entrada.requisitos, bloques)
        semanticas = self._evidencia_semantica([r for r in entrada.requisitos if r.id not in exactas], bloques)

        return [
            Diagnostico(
                requisito=r,
                exacta=exactas.get(r.id),
                semantica=semanticas.get(r.id),
                calificacion=calificar(r, exactas.get(r.id), semanticas.get(r.id), umbrales_semanticos),
            )
            for r in entrada.requisitos
        ]

    def _evidencia_exacta(
        self, requisitos: Sequence[RequisitoInput], bloques: list[BloqueDeSeccion]
    ) -> dict[int, CompetenciaDetectada]:
        # Cada requisito se busca como una competencia de catálogo, con sus sinónimos.
        catalogo = [CompetenciaCatalogo(id=r.id, nombre=r.competencia, sinonimos=r.sinonimos) for r in requisitos]
        analizador = AnalizadorDeCv(catalogo, self._hoy)
        return {d.competencia_id: d for d in analizador.consolidar(analizador.menciones_en(bloques))}

    def _evidencia_semantica(
        self, requisitos: Sequence[RequisitoInput], bloques: list[BloqueDeSeccion]
    ) -> dict[int, EvidenciaSemantica]:
        # El modelo solo se usa si algún requisito no tiene mención literal.
        if not requisitos:
            return {}
        return BuscadorSemantico(self._embedder, fragmentos.descriptivos(bloques, self._hoy)).mas_parecidos(requisitos)

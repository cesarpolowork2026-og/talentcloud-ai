"""Resumen de coincidencias y brechas de una postulación."""

from collections.abc import Sequence

from talentcloud_ai.contrato.comun import NivelCoincidencia, TipoRequisito
from talentcloud_ai.contrato.matching import RequisitoInput, RequisitoMatch


def _lista(nombres: Sequence[str]) -> str:
    return nombres[0] if len(nombres) == 1 else ", ".join(nombres[:-1]) + " y " + nombres[-1]


def resumir(requisitos: Sequence[RequisitoInput], resultados: Sequence[RequisitoMatch]) -> str | None:
    """Coincidencias y brechas en una frase: «Coincide en 4 de 6 requisitos. Para revisar: Kubernetes…»."""
    if not resultados:
        return None

    tipos = {r.id: r.tipo for r in requisitos}
    coinciden = sum(r.nivel is NivelCoincidencia.COINCIDE for r in resultados)
    revisar = [r.competencia for r in resultados if r.nivel is NivelCoincidencia.REVISAR]
    faltan = [
        r.competencia + (" (obligatorio)" if tipos.get(r.requisito_id) is TipoRequisito.OBLIGATORIO else "")
        for r in resultados
        if r.nivel is NivelCoincidencia.NO_ENCONTRADO
    ]

    partes = [f"Coincide en {coinciden} de {len(resultados)} requisitos."]
    if revisar:
        partes.append(f"Para revisar: {_lista(revisar)}.")
    if faltan:
        partes.append(f"Sin evidencia: {_lista(faltan)}.")
    return " ".join(partes)

"""Motivo de cada resultado, en lenguaje llano para el reclutador."""

from talentcloud_ai.contrato.comun import SeccionCv, TipoRequisito
from talentcloud_ai.contrato.cv import CompetenciaDetectada
from talentcloud_ai.contrato.matching import RequisitoInput
from talentcloud_ai.matching.calificacion import Calificacion, Razon

_DONDE = {
    SeccionCv.EXPERIENCIA: "la experiencia laboral",
    SeccionCv.PROYECTOS: "los proyectos",
    SeccionCv.PERFIL: "el perfil",
    SeccionCv.HABILIDADES: "las habilidades",
    SeccionCv.CERTIFICACIONES: "las certificaciones",
    SeccionCv.EDUCACION: "la formación",
    SeccionCv.IDIOMAS: "los idiomas",
    SeccionCv.INTERESES: "los intereses",
}


def anios_en_texto(anios: float) -> str:
    """5.0 → "5 años", 1.5 → "1,5 años", 1.0 → "1 año"."""
    cifra = f"{anios:.1f}".rstrip("0").rstrip(".").replace(".", ",")
    return f"{cifra} año" if anios == 1 else f"{cifra} años"


def similitud_en_texto(similitud: float) -> str:
    """0.6234 → "0,62"."""
    return f"{similitud:.2f}".replace(".", ",")


def motivo(  # noqa: PLR0911 - una frase por razón
    calificacion: Calificacion, requisito: RequisitoInput, exacta: CompetenciaDetectada | None
) -> str:
    competencia = requisito.competencia
    anios = exacta.anios if exacta else None
    minimo = requisito.anios_minimos
    donde = _DONDE.get(calificacion.seccion, "el CV") if calificacion.seccion else "el CV"
    similitud = similitud_en_texto(calificacion.similitud) if calificacion.similitud is not None else ""

    match calificacion.razon:
        case Razon.CUMPLE_ANIOS if anios is not None and minimo is not None:
            return f"Coincidencia exacta: el CV acredita {anios_en_texto(anios)} con {competencia} (se piden {minimo})."
        case Razon.COINCIDENCIA_EXACTA if anios is not None:
            return f"Coincidencia exacta en {donde}, con {anios_en_texto(anios)} de experiencia."
        case Razon.COINCIDENCIA_EXACTA | Razon.CUMPLE_ANIOS:
            return f"Coincidencia exacta: el CV menciona {competencia} en {donde}."
        case Razon.ANIOS_INSUFICIENTES if anios is not None:
            return f"El CV acredita {anios_en_texto(anios)} con {competencia}; se piden {minimo}."
        case Razon.ANIOS_NO_INDICADOS | Razon.ANIOS_INSUFICIENTES:
            return f"El CV menciona {competencia}, pero no indica cuántos años de experiencia tiene; se piden {minimo}."
        case Razon.SOLO_INTERES:
            return f"Se menciona {competencia} como interés o nivel básico, sin experiencia declarada."
        case Razon.SIMILITUD_ALTA:
            return (
                f"Similitud semántica alta ({similitud}) con {donde}: el CV no nombra {competencia}, "
                "pero describe experiencia relacionada."
            )
        case Razon.SIMILITUD_PARCIAL:
            return (
                f"Similitud semántica parcial ({similitud}) con {donde}: "
                f"puede estar relacionado con {competencia}; conviene revisarlo."
            )
        case Razon.SIN_EVIDENCIA:
            texto = f"No se encontró evidencia de {competencia} en el CV."
            if requisito.tipo is TipoRequisito.OBLIGATORIO:
                texto += " Es un requisito obligatorio."
            return texto

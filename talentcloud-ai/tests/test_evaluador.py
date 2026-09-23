from collections.abc import Callable

import pytest

from talentcloud_ai.contrato.comun import NivelCoincidencia, SeccionCv, TipoRequisito
from talentcloud_ai.contrato.cv import CompetenciaDetectada
from talentcloud_ai.contrato.matching import MatchInput, MatchResult, RequisitoInput
from talentcloud_ai.explicabilidad.motivos import anios_en_texto
from talentcloud_ai.explicabilidad.motivos import motivo as explicar
from talentcloud_ai.matching import umbrales
from talentcloud_ai.matching.evaluador import EvaluadorDeMatching, calificar, nivel_de
from tests.conftest import HOY


def requisito(
    competencia: str,
    anios_minimos: int | None = None,
    tipo: TipoRequisito = TipoRequisito.OBLIGATORIO,
    peso: int = 3,
    id_: int = 1,
) -> RequisitoInput:
    return RequisitoInput(
        id=id_, competencia=competencia, sinonimos=[], tipo=tipo, peso=peso, anios_minimos=anios_minimos
    )


def detectada(
    anios: float | None = None, dudosa: bool = False, seccion: SeccionCv = SeccionCv.EXPERIENCIA
) -> CompetenciaDetectada:
    return CompetenciaDetectada(
        competencia_id=1, nombre="Java", anios=anios, evidencia="…", seccion=seccion, dudosa=dudosa
    )


def evaluar(ejemplo: Callable[[str], dict[str, object]]) -> MatchResult:
    return EvaluadorDeMatching(HOY).evaluar(MatchInput.model_validate(ejemplo("match_input")))


# — Calificación de un requisito —


@pytest.mark.parametrize(
    ("minimo", "anios", "porcentaje", "nivel"),
    [
        (3, 5.0, 94, NivelCoincidencia.COINCIDE),
        (3, 3.0, 90, NivelCoincidencia.COINCIDE),
        (2, 20.0, umbrales.MAXIMO, NivelCoincidencia.COINCIDE),
        (4, 2.0, 57, NivelCoincidencia.REVISAR),
        (3, None, umbrales.ANIOS_NO_INDICADOS, NivelCoincidencia.REVISAR),
    ],
)
def test_califica_segun_los_anios_exigidos(
    minimo: int, anios: float | None, porcentaje: int, nivel: NivelCoincidencia
) -> None:
    calificacion = calificar(requisito("Java", minimo), detectada(anios))

    assert calificacion.porcentaje == porcentaje
    assert nivel_de(calificacion.porcentaje) is nivel


def test_valora_mas_la_evidencia_laboral_que_una_lista_de_habilidades() -> None:
    laboral = calificar(requisito("Java"), detectada(seccion=SeccionCv.EXPERIENCIA))
    lista = calificar(requisito("Java"), detectada(seccion=SeccionCv.HABILIDADES))

    assert laboral.porcentaje == umbrales.BASE_MENCION + umbrales.BONO_EXPERIENCIA_LABORAL
    assert lista.porcentaje == umbrales.BASE_MENCION


def test_una_mencion_dudosa_queda_para_revisar() -> None:
    calificacion = calificar(requisito("Kubernetes", 2), detectada(dudosa=True))
    assert (calificacion.porcentaje, nivel_de(calificacion.porcentaje)) == (20, NivelCoincidencia.REVISAR)


def test_sin_evidencia_no_encontrado() -> None:
    assert nivel_de(calificar(requisito("AWS"), None).porcentaje) is NivelCoincidencia.NO_ENCONTRADO


def test_escribe_los_anios_en_espanol() -> None:
    assert [anios_en_texto(a) for a in (1.0, 1.5, 5.0, 6.7)] == ["1 año", "1,5 años", "5 años", "6,7 años"]


# — Evaluación completa: el ejemplo del contrato es la vacante "Desarrollador Backend" y el CV de Elena —


def test_evalua_a_elena_como_en_el_prototipo(ejemplo: Callable[[str], dict[str, object]]) -> None:
    resultado = evaluar(ejemplo)
    niveles = {r.competencia: r.nivel for r in resultado.requisitos}

    assert niveles == {
        "Java": NivelCoincidencia.COINCIDE,
        "Spring Boot": NivelCoincidencia.COINCIDE,
        "PostgreSQL": NivelCoincidencia.COINCIDE,
        "Docker": NivelCoincidencia.COINCIDE,
        "Kubernetes": NivelCoincidencia.REVISAR,
        "Certificación AWS": NivelCoincidencia.NO_ENCONTRADO,
    }
    java = resultado.requisitos[0]
    assert java.evidencia is not None
    assert "5 años de experiencia con Java" in java.evidencia
    assert java.motivo == "Coincidencia exacta: el CV acredita 5 años con Java (se piden 3)."
    assert resultado.resumen == (
        "Coincide en 4 de 6 requisitos. Para revisar: Kubernetes. Sin evidencia: Certificación AWS."
    )


def test_senala_siempre_el_requisito_obligatorio_sin_evidencia(ejemplo: Callable[[str], dict[str, object]]) -> None:
    entrada = MatchInput.model_validate(ejemplo("match_input"))
    obligatorio = requisito("Terraform", tipo=TipoRequisito.OBLIGATORIO, id_=99)
    resultado = EvaluadorDeMatching(HOY).evaluar(entrada.model_copy(update={"requisitos": [obligatorio]}))

    assert (
        resultado.requisitos[0].motivo == "No se encontró evidencia de Terraform en el CV. Es un requisito obligatorio."
    )
    assert resultado.resumen is not None
    assert resultado.resumen.endswith("Sin evidencia: Terraform (obligatorio).")


def test_pondera_el_puntaje_global_por_peso(ejemplo: Callable[[str], dict[str, object]]) -> None:
    resultado = evaluar(ejemplo)
    entrada = MatchInput.model_validate(ejemplo("match_input"))
    esperado = sum(r.peso * m.porcentaje for r, m in zip(entrada.requisitos, resultado.requisitos, strict=True)) / sum(
        r.peso for r in entrada.requisitos
    )

    assert resultado.score == round(esperado, 2)


def test_una_vacante_sin_requisitos_da_cero(ejemplo: Callable[[str], dict[str, object]]) -> None:
    entrada = MatchInput.model_validate(ejemplo("match_input")).model_copy(update={"requisitos": []})
    resultado = EvaluadorDeMatching(HOY).evaluar(entrada)

    assert (resultado.score, resultado.requisitos, resultado.resumen) == (0.0, [], None)


@pytest.mark.parametrize(
    ("minimo", "anios", "seccion", "motivo"),
    [
        (None, None, SeccionCv.HABILIDADES, "Coincidencia exacta: el CV menciona Java en las habilidades."),
        (
            None,
            4.7,
            SeccionCv.EXPERIENCIA,
            "Coincidencia exacta en la experiencia laboral, con 4,7 años de experiencia.",
        ),
        (4, 1.5, SeccionCv.EXPERIENCIA, "El CV acredita 1,5 años con Java; se piden 4."),
        (
            3,
            None,
            SeccionCv.PERFIL,
            "El CV menciona Java, pero no indica cuántos años de experiencia tiene; se piden 3.",
        ),
    ],
)
def test_explica_cada_resultado_en_lenguaje_llano(
    minimo: int | None, anios: float | None, seccion: SeccionCv, motivo: str
) -> None:
    requisito_java = requisito("Java", minimo)
    java = detectada(anios, seccion=seccion)

    assert explicar(calificar(requisito_java, java).razon, requisito_java, java) == motivo

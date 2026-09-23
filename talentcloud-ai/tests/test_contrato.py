"""El contrato con Laravel (versión 1) no puede cambiar sin querer: los ejemplos los validan ambos lados."""

from collections.abc import Callable

from fastapi.testclient import TestClient

from talentcloud_ai.contrato.comun import Motor
from talentcloud_ai.contrato.matching import MatchInput, MatchResult

CLAVES_MATCH_INPUT = {"version", "postulacion_id", "vacante", "requisitos", "cv"}
CLAVES_REQUISITO_INPUT = {"id", "competencia", "sinonimos", "tipo", "peso", "anios_minimos", "descripcion"}
CLAVES_MATCH_RESULT = {"version", "score", "motor", "resumen", "requisitos"}
CLAVES_REQUISITO_MATCH = {"requisito_id", "competencia", "porcentaje", "nivel", "evidencia", "motivo"}


def test_el_ejemplo_de_entrada_es_lo_que_envia_laravel(ejemplo: Callable[[str], dict[str, object]]) -> None:
    datos = ejemplo("match_input")
    entrada = MatchInput.model_validate(datos)

    assert set(datos) == CLAVES_MATCH_INPUT
    assert set(entrada.model_dump()) == CLAVES_MATCH_INPUT
    assert set(entrada.requisitos[0].model_dump()) == CLAVES_REQUISITO_INPUT
    assert entrada.model_dump(mode="json") == datos


def test_la_salida_tiene_exactamente_las_claves_que_lee_laravel(ejemplo: Callable[[str], dict[str, object]]) -> None:
    resultado = MatchResult.model_validate(ejemplo("match_result"))
    serializado = resultado.model_dump(mode="json")

    assert set(serializado) == CLAVES_MATCH_RESULT
    assert set(serializado["requisitos"][0]) == CLAVES_REQUISITO_MATCH
    assert serializado == ejemplo("match_result")
    assert resultado.motor is Motor.HTTP


def test_la_entrada_tolera_campos_nuevos(ejemplo: Callable[[str], dict[str, object]]) -> None:
    entrada = MatchInput.model_validate({**ejemplo("match_input"), "campo_futuro": True})
    assert "campo_futuro" not in entrada.model_dump()


CLAVES_ANALISIS_CV = {
    "version",
    "secciones",
    "competencias",
    "experiencia_total_anios",
    "cargos",
    "certificaciones",
    "fragmentos",
    "modelo_embeddings",
}


def test_el_analisis_de_cv_es_el_del_ejemplo(cliente: TestClient, ejemplo: Callable[[str], dict[str, object]]) -> None:
    respuesta = cliente.post("/cv/analizar", json=ejemplo("analisis_cv_input"))

    assert respuesta.status_code == 200
    assert set(respuesta.json()) == CLAVES_ANALISIS_CV
    assert respuesta.json() == ejemplo("analisis_cv")

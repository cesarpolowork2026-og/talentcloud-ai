import math
from collections.abc import Callable

import pytest
from fastapi.testclient import TestClient

from talentcloud_ai.api.dependencias import hoy
from talentcloud_ai.contrato.comun import MAX_TEXTO_CV
from talentcloud_ai.embeddings.determinista import EmbedderDeterminista
from talentcloud_ai.main import crear_app
from tests.conftest import configuracion


def test_informa_su_estado_sin_clave() -> None:
    with TestClient(crear_app(configuracion())) as cliente:
        respuesta = cliente.get("/health")

    assert respuesta.status_code == 200
    assert respuesta.json()["estado"] == "ok"
    assert respuesta.json()["modelo_embeddings"] == "determinista-hashing"
    assert respuesta.json()["dimensiones_embeddings"] == 384


def test_exige_la_clave_de_api_en_los_endpoints_protegidos(
    cliente: TestClient, ejemplo: Callable[[str], dict[str, object]]
) -> None:
    sin_clave = cliente.post("/matching/evaluar", json=ejemplo("match_input"), headers={"X-Api-Key": ""})
    clave_erronea = cliente.post("/matching/evaluar", json=ejemplo("match_input"), headers={"X-Api-Key": "otra"})

    assert sin_clave.status_code == 401
    assert clave_erronea.status_code == 401
    assert clave_erronea.json() == {"detail": "Clave de API no válida."}


def test_evalua_una_postulacion(cliente: TestClient, ejemplo: Callable[[str], dict[str, object]]) -> None:
    respuesta = cliente.post("/matching/evaluar", json=ejemplo("match_input"))

    assert respuesta.status_code == 200
    assert respuesta.json() == ejemplo("match_result")


def test_rechaza_una_version_de_contrato_desconocida(
    cliente: TestClient, ejemplo: Callable[[str], dict[str, object]]
) -> None:
    respuesta = cliente.post("/matching/evaluar", json={**ejemplo("match_input"), "version": 2})
    assert respuesta.status_code == 422


def test_rechaza_cuerpos_fuera_de_limites(cliente: TestClient, ejemplo: Callable[[str], dict[str, object]]) -> None:
    entrada = ejemplo("match_input")
    enorme = {**entrada, "cv": {"id": 1, "texto": "x" * (MAX_TEXTO_CV + 1)}}
    peso_invalido = {**entrada, "requisitos": [{"id": 1, "competencia": "Java", "tipo": "obligatorio", "peso": 9}]}

    assert cliente.post("/matching/evaluar", json=enorme).status_code == 422
    assert cliente.post("/matching/evaluar", json=peso_invalido).status_code == 422


def test_analiza_un_cv(cliente: TestClient, cv: Callable[[str], str]) -> None:
    catalogo = [{"id": 7, "nombre": "Java", "sinonimos": []}, {"id": 8, "nombre": "Kubernetes", "sinonimos": ["k8s"]}]
    respuesta = cliente.post(
        "/cv/analizar", json={"version": 1, "cv": {"id": 3, "texto": cv("elena")}, "catalogo": catalogo}
    )

    assert respuesta.status_code == 200
    datos = respuesta.json()
    assert {c["competencia_id"]: c["dudosa"] for c in datos["competencias"]} == {7: False, 8: True}
    assert datos["cargos"][0]["organizacion"] == "Fintech Andina"
    assert all(f["seccion"] != "encabezado" for f in datos["fragmentos"])
    assert datos["modelo_embeddings"] == "determinista-hashing"
    assert all(len(f["embedding"]) == 384 for f in datos["fragmentos"])


def test_genera_embeddings_normalizados(cliente: TestClient) -> None:
    textos = [{"id": 1, "texto": "Java con Spring Boot"}, {"id": 2, "texto": "Kubernetes en producción"}]
    respuesta = cliente.post("/requisitos/embeddings", json={"version": 1, "textos": textos})

    assert respuesta.status_code == 200
    datos = respuesta.json()
    assert datos["dimensiones"] == 384
    assert [e["id"] for e in datos["embeddings"]] == [1, 2]
    for embedding in datos["embeddings"]:
        assert len(embedding["vector"]) == 384
        assert math.isclose(math.sqrt(sum(v * v for v in embedding["vector"])), 1.0)


def test_el_embedder_determinista_es_estable_y_distingue_textos() -> None:
    embedder = EmbedderDeterminista(16)
    a, b, c = embedder.codificar(["Java y Docker", "java y docker", "Figma"])

    assert a == b
    assert a != c
    assert embedder.codificar([""]) == [[0.0] * 16]


def test_oculta_la_documentacion_en_produccion() -> None:
    with TestClient(crear_app(configuracion(entorno="production", api_key="clave-de-prueba"))) as cliente:
        assert cliente.get("/docs").status_code == 404
        assert cliente.get("/openapi.json").status_code == 404


def test_no_filtra_detalles_de_errores_inesperados(
    cliente: TestClient, ejemplo: Callable[[str], dict[str, object]]
) -> None:
    def fallar() -> None:
        raise RuntimeError("detalle interno")

    cliente.app.dependency_overrides[hoy] = fallar  # type: ignore[attr-defined]
    respuesta = TestClient(cliente.app, raise_server_exceptions=False, headers={"X-Api-Key": "clave-de-prueba"}).post(
        "/matching/evaluar", json=ejemplo("match_input")
    )

    assert respuesta.status_code == 500
    assert respuesta.json() == {"detail": "Error interno del servicio de IA."}


def test_no_arranca_en_produccion_sin_clave_de_api() -> None:
    with pytest.raises(RuntimeError, match="TALENTCLOUD_AI_API_KEY es obligatoria"):
        # api_key=None explícito: el .env local podría traer una clave.
        crear_app(configuracion(entorno="production", api_key=None))

import math
import sys
from collections.abc import Callable

import pytest

from talentcloud_ai import casos_de_uso
from talentcloud_ai.contrato.cv import AnalisisCvInput, CompetenciaCatalogo
from talentcloud_ai.contrato.embeddings import EmbeddingsInput
from talentcloud_ai.embeddings import fabrica
from talentcloud_ai.embeddings.determinista import EmbedderDeterminista
from talentcloud_ai.embeddings.fabrica import BackendNoDisponibleError, DimensionesIncompatiblesError, crear_embedder
from tests.conftest import HOY, configuracion


def coseno(a: list[float], b: list[float]) -> float:
    return sum(x * y for x, y in zip(a, b, strict=True))  # vectores de norma 1


def test_crea_el_embedder_configurado() -> None:
    embedder = crear_embedder(configuracion())
    assert (embedder.modelo, embedder.dimensiones) == ("determinista-hashing", 384)


def test_no_arranca_si_el_modelo_no_da_las_dimensiones_de_la_base_de_datos(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setattr(fabrica, "_construir", lambda _settings: EmbedderDeterminista(16))

    with pytest.raises(DimensionesIncompatiblesError, match="16 dimensiones; se esperaban 384"):
        crear_embedder(configuracion())


def test_explica_como_instalar_el_modelo_si_falta(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setitem(sys.modules, "talentcloud_ai.embeddings.sentence_transformers", None)

    with pytest.raises(BackendNoDisponibleError, match="uv sync --extra ml"):
        crear_embedder(configuracion(embeddings_backend="sentence-transformers"))


def test_analiza_el_cv_con_un_embedding_por_fragmento(
    cv: Callable[[str], str], catalogo: list[CompetenciaCatalogo]
) -> None:
    entrada = AnalisisCvInput.model_validate(
        {"version": 1, "cv": {"id": 1, "texto": cv("sofia")}, "catalogo": catalogo}
    )
    embedder = EmbedderDeterminista()

    analisis = casos_de_uso.analizar_cv(entrada, embedder, HOY)

    assert analisis.modelo_embeddings == "determinista-hashing"
    assert analisis.fragmentos
    for fragmento in analisis.fragmentos:
        assert fragmento.embedding == embedder.codificar([fragmento.texto])[0]


def test_los_embeddings_de_requisitos_conservan_el_id() -> None:
    entrada = EmbeddingsInput.model_validate(
        {"version": 1, "textos": [{"id": 9, "texto": "Java"}, {"id": 4, "texto": "Docker"}]}
    )
    resultado = casos_de_uso.generar_embeddings(entrada, EmbedderDeterminista())

    assert [e.id for e in resultado.embeddings] == [9, 4]


@pytest.mark.modelo
def test_el_modelo_real_capta_el_significado() -> None:
    pytest.importorskip("sentence_transformers")
    embedder = crear_embedder(configuracion(embeddings_backend="sentence-transformers"))

    experiencia, requisito, otro = embedder.codificar(
        [
            "Desarrollé APIs REST con Spring Boot para una plataforma de pagos.",
            "Experiencia construyendo servicios backend con el framework Spring.",
            "Diseño de interfaces y prototipos de alta fidelidad en Figma.",
        ]
    )

    assert embedder.dimensiones == 384
    assert math.isclose(math.sqrt(sum(v * v for v in experiencia)), 1.0, rel_tol=1e-5)
    assert coseno(experiencia, requisito) > coseno(experiencia, otro) + 0.2

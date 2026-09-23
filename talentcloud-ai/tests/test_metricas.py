from pathlib import Path

import pytest

from talentcloud_ai.conjunto_de_evaluacion import ConjuntoDeEvaluacion
from talentcloud_ai.contrato.comun import NivelCoincidencia
from talentcloud_ai.embeddings.determinista import EmbedderDeterminista
from talentcloud_ai.matching.evaluador import EvaluadorDeMatching
from talentcloud_ai.metricas import PrecisionRecall, extraccion, niveles

C, R, N = NivelCoincidencia.COINCIDE, NivelCoincidencia.REVISAR, NivelCoincidencia.NO_ENCONTRADO
RAIZ = Path(__file__).parents[1]


def test_mide_precision_y_recall_de_la_extraccion() -> None:
    medida = extraccion(
        {"elena": {"Java", "Docker"}, "ivan": {"Figma"}},
        {"elena": {"Java", "Kubernetes"}, "ivan": {"Figma"}},
    )

    assert (medida.verdaderos, medida.falsos_positivos, medida.falsos_negativos) == (2, 1, 1)
    assert medida.precision == pytest.approx(2 / 3)
    assert medida.recall == pytest.approx(2 / 3)
    assert medida.f1 == pytest.approx(2 / 3)


def test_sin_predicciones_ni_casos_no_divide_por_cero() -> None:
    vacia = PrecisionRecall(0, 0, 0)
    assert (vacia.precision, vacia.recall) == (1.0, 1.0)
    assert niveles([]).exactitud == 0.0


def test_mide_el_acierto_del_nivel_y_los_falsos_coincide() -> None:
    medida = niveles([(C, C), (R, C), (N, C), (N, N)])

    assert (medida.aciertos, medida.total, medida.exactitud) == (2, 4, 0.5)
    assert medida.falsos_coincide == 1  # solo el "no encontrado" dado por "coincide"
    assert medida.por_nivel(C).precision == pytest.approx(1 / 3)
    assert medida.matriz_de_confusion()["no_encontrado"] == {"coincide": 1, "revisar": 0, "no_encontrado": 1}


def test_el_conjunto_de_evaluacion_es_coherente() -> None:
    conjunto = ConjuntoDeEvaluacion(RAIZ)

    assert conjunto.cvs == ["elena", "sofia", "marco", "ivan", "lucia"]
    assert all(set(r["esperado"]) == set(conjunto.cvs) for r in conjunto.requisitos)
    assert {r["via"] for r in conjunto.requisitos} == {"catalogo", "semantica"}
    assert set(conjunto.competencias_esperadas()) == set(conjunto.cvs)


def test_arma_un_caso_por_cv_y_requisito() -> None:
    conjunto = ConjuntoDeEvaluacion(RAIZ)

    casos = conjunto.casos(EvaluadorDeMatching(conjunto.fecha_referencia, EmbedderDeterminista()))

    assert len(casos) == len(conjunto.cvs) * len(conjunto.requisitos)
    java_de_marco = next(c for c in casos if c.grupo == "marco" and c.diagnostico.requisito.competencia == "Java")
    assert java_de_marco.esperado is R  # 2,7 años de los 3 pedidos

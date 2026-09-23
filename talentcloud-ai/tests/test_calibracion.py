import json
from pathlib import Path

import pytest

from talentcloud_ai.calibracion import (
    SOLO_EXACTA,
    Caso,
    buscar_umbrales,
    matriz_de_confusion,
    medir,
    validacion_cruzada,
)
from talentcloud_ai.contrato.comun import NivelCoincidencia, SeccionCv, TipoRequisito
from talentcloud_ai.contrato.matching import RequisitoInput
from talentcloud_ai.matching import umbrales
from talentcloud_ai.matching.calificacion import CALIBRADOS, UmbralesSemanticos, calificar
from talentcloud_ai.matching.evaluador import Diagnostico
from talentcloud_ai.matching.semantica import EvidenciaSemantica

INFORME = Path(__file__).parents[1] / "evaluacion" / "calibracion.json"
C, R, N = NivelCoincidencia.COINCIDE, NivelCoincidencia.REVISAR, NivelCoincidencia.NO_ENCONTRADO


def caso(similitud: float, esperado: NivelCoincidencia, grupo: str = "cv") -> Caso:
    requisito = RequisitoInput(id=1, competencia="Orquestación", tipo=TipoRequisito.OBLIGATORIO, peso=3)
    semantica = EvidenciaSemantica("fragmento", SeccionCv.EXPERIENCIA, similitud)
    diagnostico = Diagnostico(requisito, None, semantica, calificar(requisito, None, semantica))
    return Caso(grupo, diagnostico, esperado)


# Separables y con la misma estructura en cada grupo (CV): coincide 0.8, revisar 0.55, no encontrado 0.3.
CASOS = [c for grupo in ("a", "b", "c") for c in (caso(0.8, C, grupo), caso(0.55, R, grupo), caso(0.3, N, grupo))]


def test_mide_aciertos_y_falsos_coincide() -> None:
    medicion = medir(CASOS, UmbralesSemanticos(revisar=0.2, coincide=0.25))

    assert medicion.total == 9
    assert medicion.falsos_coincide == 3  # los 0.3 esperados "no encontrado"
    assert medicion.exactitud == pytest.approx(3 / 9)


def test_encuentra_umbrales_que_separan_los_casos() -> None:
    mejor = buscar_umbrales(CASOS, paso=0.05)

    assert mejor.aciertos == len(CASOS)
    assert mejor.falsos_coincide == 0
    # A igualdad de aciertos elige los umbrales más altos (más prudentes).
    assert (mejor.umbrales.revisar, mejor.umbrales.coincide) == (0.55, 0.8)


def test_la_linea_base_no_usa_la_similitud() -> None:
    assert all(c.predecir(SOLO_EXACTA) is N for c in CASOS)


def test_valida_dejando_un_grupo_fuera() -> None:
    assert validacion_cruzada(CASOS, paso=0.05) == pytest.approx(1.0)


def test_resume_los_errores_en_una_matriz_de_confusion() -> None:
    matriz = matriz_de_confusion(CASOS, UmbralesSemanticos(revisar=0.45, coincide=0.9))

    assert matriz["coincide"] == {"coincide": 0, "revisar": 3, "no_encontrado": 0}
    assert sum(sum(fila.values()) for fila in matriz.values()) == len(CASOS)


def test_el_codigo_usa_los_umbrales_calibrados() -> None:
    informe = json.loads(INFORME.read_text(encoding="utf-8"))

    assert (
        informe["umbrales"]["revisar"],
        informe["umbrales"]["coincide"],
    ) == (umbrales.SIMILITUD_REVISAR, umbrales.SIMILITUD_COINCIDE)
    assert (CALIBRADOS.revisar, CALIBRADOS.coincide) == (umbrales.SIMILITUD_REVISAR, umbrales.SIMILITUD_COINCIDE)
    # El informe se generó con los umbrales vigentes y sin falsos "Coincide".
    assert informe["exactitud"]["umbrales_vigentes_en_codigo"] == informe["exactitud"]["hibrido_calibrado"]
    assert informe["falsos_coincide"]["hibrido_calibrado"] == 0

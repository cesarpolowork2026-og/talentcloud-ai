from collections.abc import Callable

import pytest

from talentcloud_ai.contrato.comun import SeccionCv
from talentcloud_ai.contrato.cv import AnalisisCv, CompetenciaCatalogo, CompetenciaDetectada
from talentcloud_ai.extraccion.analizador import AnalizadorDeCv
from talentcloud_ai.extraccion.competencias import DetectorDeCompetencias, es_mencion_dudosa
from talentcloud_ai.extraccion.fragmentos import MAX_CARACTERES
from talentcloud_ai.extraccion.texto import normalizar_alineado
from tests.conftest import HOY


def analizar(texto: str, catalogo: list[CompetenciaCatalogo]) -> AnalisisCv:
    return AnalizadorDeCv(catalogo, HOY).analizar(texto)


def por_nombre(analisis: AnalisisCv) -> dict[str, CompetenciaDetectada]:
    return {c.nombre: c for c in analisis.competencias}


# — Detección de competencias —


def test_detecta_competencias_por_sinonimo(catalogo: list[CompetenciaCatalogo]) -> None:
    encontradas = DetectorDeCompetencias(catalogo).buscar("Bases de datos en Postgres y despliegues en k8s.")
    assert {e.nombre for e in encontradas} == {"PostgreSQL", "Kubernetes"}


def test_respeta_mayusculas_en_terminos_cortos(catalogo: list[CompetenciaCatalogo]) -> None:
    detector = DetectorDeCompetencias(catalogo)

    assert {e.nombre for e in detector.buscar("Servicios en Go y front en TS.")} == {"Go", "TypeScript"}
    # "go" en minúsculas es una palabra corriente, no el lenguaje.
    assert detector.buscar("Let's go, ts.") == []


def test_no_confunde_competencias_contenidas_en_otras(catalogo: list[CompetenciaCatalogo]) -> None:
    encontradas = DetectorDeCompetencias(catalogo).buscar("Frontend con JavaScript y consultas en PostgreSQL.")
    assert {e.nombre for e in encontradas} == {"JavaScript", "PostgreSQL"}


@pytest.mark.parametrize(
    ("oracion", "termino", "seccion", "dudosa"),
    [
        ("Kubernetes (interés en aprender) y arquitectura en la nube.", "kubernetes", SeccionCv.HABILIDADES, True),
        ("Figma, research, nociones de Java y HTML.", "java", SeccionCv.HABILIDADES, True),
        ("Java, Python (básico).", "java", SeccionCv.HABILIDADES, False),
        ("Java, Python (básico).", "python", SeccionCv.HABILIDADES, True),
        ("Docker en producción.", "docker", SeccionCv.INTERESES, True),
    ],
)
def test_marca_como_dudosas_solo_las_menciones_sin_experiencia(
    oracion: str, termino: str, seccion: SeccionCv, dudosa: bool
) -> None:
    normal = normalizar_alineado(oracion)
    assert es_mencion_dudosa(normal, normal.index(termino), seccion) is dudosa


# — Análisis completo sobre los CV de demostración —


def test_analiza_el_cv_de_elena(cv: Callable[[str], str], catalogo: list[CompetenciaCatalogo]) -> None:
    analisis = analizar(cv("elena"), catalogo)
    competencias = por_nombre(analisis)

    assert [s.nombre for s in analisis.secciones][:3] == [SeccionCv.ENCABEZADO, SeccionCv.PERFIL, SeccionCv.EXPERIENCIA]

    java = competencias["Java"]
    assert java.anios == 5.0
    assert java.evidencia.startswith("Desarrolladora backend con 5 años de experiencia con Java")
    assert not java.dudosa

    # Usó Spring Boot en los dos puestos: 2020 – actualidad.
    assert competencias["Spring Boot"].anios == pytest.approx(6.7, abs=0.05)
    assert competencias["PostgreSQL"].seccion is SeccionCv.EXPERIENCIA

    kubernetes = competencias["Kubernetes"]
    assert kubernetes.dudosa
    assert kubernetes.anios is None
    assert "interés en aprender" in kubernetes.evidencia

    assert "Certificación AWS" not in competencias
    assert analisis.certificaciones == []


def test_lee_puestos_y_experiencia_total(cv: Callable[[str], str], catalogo: list[CompetenciaCatalogo]) -> None:
    analisis = analizar(cv("marco"), catalogo)

    assert [(c.cargo, c.organizacion, c.desde, c.hasta) for c in analisis.cargos] == [
        ("Desarrollador Junior", "Soluciones Web SAC", 2024, None),
        ("Prácticas profesionales", "Municipalidad Distrital", 2023, 2023),
    ]
    assert analisis.experiencia_total_anios == pytest.approx(3.7, abs=0.05)
    assert analisis.cargos[0].evidencia == "Desarrollador Junior — Soluciones Web SAC (2024 – actualidad)"


CV_CON_FECHAS_A_LA_DERECHA = """Rosa Quispe Lazo
Ingeniera de Software
+51 900 000 001 · rosa.quispe@correo.com · Lima

PERFIL PROFESIONAL
Desarrolladora con experiencia en servicios backend.

EXPERIENCIA PROFESIONAL
Desarrolladora Backend | Andes Pagos S.A.C.\tEne. 2024 – May. 2026 (2 años y 5 meses)
●\tDiseñé APIs con Java y Spring Boot para pagos con tarjeta.
●\tReduje el tiempo de respuesta en 40 % durante 2025
Practicante de Sistemas | Municipalidad Distrital\tMar. 2023 – Dic. 2023 (10 meses)
●\tReportes con SQL para el área de rentas.

PROYECTOS ACADÉMICOS Y PERSONALES
●\tChatbot de atención con Python — proyecto del curso de IA.
"""


def test_lee_cv_con_fechas_a_la_derecha_duracion_y_vinetas(catalogo: list[CompetenciaCatalogo]) -> None:
    analisis = analizar(CV_CON_FECHAS_A_LA_DERECHA, catalogo)

    # Los dos puestos, con sus fechas, y ningún logro confundido con un puesto.
    assert [(c.cargo, c.organizacion, c.desde, c.hasta) for c in analisis.cargos] == [
        ("Desarrolladora Backend", "Andes Pagos S.A.C", 2024, 2026),
        ("Practicante de Sistemas", "Municipalidad Distrital", 2023, 2023),
    ]
    # La experiencia de cada competencia sale del puesto donde se usó.
    assert por_nombre(analisis)["Java"].anios == pytest.approx(2.4, abs=0.05)
    assert por_nombre(analisis)["SQL"].anios == pytest.approx(0.8, abs=0.05)
    assert por_nombre(analisis)["Python"].seccion is SeccionCv.PROYECTOS
    # El logro sigue siendo evidencia para el matching semántico.
    assert "Reduje el tiempo de respuesta en 40 % durante 2025" in [f.texto for f in analisis.fragmentos]


def test_toma_la_experiencia_del_perfil_si_no_hay_puestos_con_fechas(catalogo: list[CompetenciaCatalogo]) -> None:
    analisis = analizar("Perfil\nAnalista con 8 años de experiencia en SQL.", catalogo)

    assert analisis.cargos == []
    assert analisis.experiencia_total_anios == 8.0
    assert por_nombre(analisis)["SQL"].anios == 8.0


def test_extrae_certificaciones_con_su_origen(cv: Callable[[str], str], catalogo: list[CompetenciaCatalogo]) -> None:
    sofia = analizar(cv("sofia"), catalogo)
    lucia = analizar(cv("lucia"), catalogo)

    assert [c.nombre for c in sofia.certificaciones] == ["AWS Certified Developer – Associate"]
    assert [c.nombre for c in lucia.certificaciones] == ["AWS Solutions Architect – Associate"]
    assert lucia.certificaciones[0].evidencia == "Obtuve la certificación AWS Solutions Architect – Associate."
    assert "Certificación AWS" in por_nombre(lucia)


def test_los_fragmentos_excluyen_los_datos_personales(
    cv: Callable[[str], str], catalogo: list[CompetenciaCatalogo]
) -> None:
    texto = cv("elena") + "\nDatos personales\nFecha de nacimiento: 1994. Nacionalidad peruana.\n"
    analisis = analizar(texto, catalogo)
    textos = " ".join(f.texto for f in analisis.fragmentos)

    assert "Elena Ruiz" not in textos
    assert "elena.ruiz@correo.com" not in textos
    assert "nacimiento" not in textos
    assert SeccionCv.ENCABEZADO not in {f.seccion for f in analisis.fragmentos}
    assert [f.orden for f in analisis.fragmentos] == list(range(len(analisis.fragmentos)))


def test_parte_las_lineas_largas_en_fragmentos_acotados(catalogo: list[CompetenciaCatalogo]) -> None:
    linea = " ".join(f"Oración número {i} sobre servicios con Java en producción." for i in range(40))
    analisis = analizar(f"Experiencia\n{linea}", catalogo)

    assert len(analisis.fragmentos) > 1
    assert all(len(f.texto) <= MAX_CARACTERES for f in analisis.fragmentos)


def test_no_analiza_competencias_en_referencias(catalogo: list[CompetenciaCatalogo]) -> None:
    analisis = analizar("Referencias\nAna Pérez, jefa de Java en Banco Sur.", catalogo)
    assert analisis.competencias == []


def test_un_cv_vacio_no_falla(catalogo: list[CompetenciaCatalogo]) -> None:
    analisis = analizar("", catalogo)
    assert (analisis.secciones, analisis.competencias, analisis.experiencia_total_anios) == ([], [], None)

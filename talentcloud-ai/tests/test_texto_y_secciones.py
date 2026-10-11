import pytest

from talentcloud_ai.contrato.comun import SeccionCv
from talentcloud_ai.extraccion.secciones import segmentar, tipo_de_encabezado
from talentcloud_ai.extraccion.texto import (
    contiene_termino,
    lineas,
    normalizar,
    normalizar_alineado,
    oraciones,
    recortar,
)


def test_normaliza_sin_tildes_ni_mayusculas() -> None:
    assert normalizar("  Diseñé   APIs en  PostgreSQL ") == "disene apis en postgresql"


def test_la_normalizacion_alineada_conserva_las_posiciones() -> None:
    original = "Diseñé esquemas en PostgreSQL"
    alineado = normalizar_alineado(original)

    assert len(alineado) == len(original)
    posicion = alineado.index("postgresql")
    assert original[posicion : posicion + 10] == "PostgreSQL"


def test_busca_terminos_como_palabras_completas() -> None:
    assert contiene_termino("consultas en sql", "sql")
    assert not contiene_termino("consultas en postgresql", "sql")
    assert not contiene_termino("javascript", "java")


def test_divide_lineas_sin_vinetas_y_oraciones_sin_romper_nombres_tecnicos() -> None:
    assert lineas("• Java\n\n  - Spring Boot  \n") == ["Java", "Spring Boot"]
    assert oraciones("Usé Node.js en producción. Luego React.") == ["Usé Node.js en producción.", "Luego React."]


def test_recorta_la_evidencia_sin_partir_palabras() -> None:
    texto = "palabra " * 60
    recortado = recortar(texto, 50)
    assert len(recortado) <= 51
    assert recortado.endswith("palabra…")


@pytest.mark.parametrize(
    ("linea", "seccion"),
    [
        ("Experiencia", SeccionCv.EXPERIENCIA),
        ("EXPERIENCIA LABORAL:", SeccionCv.EXPERIENCIA),
        ("Formación académica", SeccionCv.EDUCACION),
        ("Habilidades técnicas", SeccionCv.HABILIDADES),
        ("Certificaciones", SeccionCv.CERTIFICACIONES),
        ("Datos personales", SeccionCv.DATOS_PERSONALES),
        ("Sobre mí", SeccionCv.PERFIL),
        ("PROYECTOS ACADÉMICOS Y PERSONALES", SeccionCv.PROYECTOS),
        ("Herramientas y tecnologías", SeccionCv.HABILIDADES),
        ("Habilidades blandas", SeccionCv.HABILIDADES),
        ("Experiencia con Java en proyectos", None),
        ("Java, Spring Boot, Docker", None),
    ],
)
def test_reconoce_encabezados_de_seccion(linea: str, seccion: SeccionCv | None) -> None:
    assert tipo_de_encabezado(linea) is seccion


def test_segmenta_el_cv_y_deja_el_nombre_en_el_encabezado(cv: object) -> None:
    bloques = segmentar("Elena Ruiz\nelena@correo.com\n\nPerfil\nBackend.\n\nExperiencia\nDev — Acme (2020 – 2022)\n")

    assert [b.nombre for b in bloques] == [SeccionCv.ENCABEZADO, SeccionCv.PERFIL, SeccionCv.EXPERIENCIA]
    assert bloques[0].lineas == ["Elena Ruiz", "elena@correo.com"]
    assert [b.orden for b in bloques] == [0, 1, 2]


def test_recuerda_que_lineas_empezaban_con_vineta() -> None:
    bloques = segmentar("Experiencia\nDev — Acme (2020 – 2022)\n• Superé la meta en 2021\n- Revisión de código")

    assert bloques[0].lineas == ["Dev — Acme (2020 – 2022)", "Superé la meta en 2021", "Revisión de código"]
    assert bloques[0].vinetas == {1, 2}


def test_ignora_encabezados_sin_contenido() -> None:
    bloques = segmentar("Perfil\nIntereses\nLeer.")
    assert [(b.nombre, b.texto) for b in bloques] == [(SeccionCv.INTERESES, "Leer.")]

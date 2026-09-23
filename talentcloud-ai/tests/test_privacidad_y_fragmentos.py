from datetime import date

import pytest

from talentcloud_ai.contrato.comun import NivelCoincidencia, SeccionCv, TipoRequisito
from talentcloud_ai.contrato.matching import RequisitoInput
from talentcloud_ai.extraccion import fragmentos
from talentcloud_ai.extraccion.competencias import es_fragmento_dudoso
from talentcloud_ai.extraccion.privacidad import es_dato_personal
from talentcloud_ai.extraccion.secciones import segmentar
from talentcloud_ai.matching.calificacion import Razon, UmbralesSemanticos, calificar
from talentcloud_ai.matching.semantica import EvidenciaSemantica, vistas_de_consulta

HOY = date(2026, 9, 23)


@pytest.mark.parametrize(
    "linea",
    [
        "ana.torres@correo.com",
        "Teléfono: +51 987 654 321",
        "987654321",
        "linkedin.com/in/ana-torres",
        "Fecha de nacimiento: 12/03/1994",
        "Nacida en Arequipa, Perú.",
        "Estado civil: soltera",
        "Género: femenino",
        "DNI: 45678912",
        "32 años de edad",
    ],
)
def test_reconoce_datos_personales(linea: str) -> None:
    assert es_dato_personal(linea)


@pytest.mark.parametrize(
    "linea",
    [
        "Backend Developer — Retail Digital (2015-2021)",
        "Genero reportes semanales con SQL.",
        "Trabajé bajo la dirección de la gerencia de TI.",
        "Desarrolladora backend con 5 años de experiencia con Java.",
        "Orquesté más de 40 microservicios con Kubernetes.",
    ],
)
def test_no_confunde_la_experiencia_con_datos_personales(linea: str) -> None:
    assert not es_dato_personal(linea)


def test_un_cv_sin_encabezados_no_queda_entero_como_encabezado() -> None:
    texto = (
        "Ana Torres\nana@correo.com\n"
        "Ingeniera de datos con cuatro años construyendo pipelines en Python para el área comercial.\n"
        "Pipelines con Airflow."
    )
    bloques = segmentar(texto)

    assert [b.nombre for b in bloques] == [SeccionCv.ENCABEZADO, SeccionCv.OTROS]
    assert bloques[0].lineas == ["Ana Torres", "ana@correo.com"]
    assert [f.texto for f in fragmentos.construir(bloques)] == [
        "Ingeniera de datos con cuatro años construyendo pipelines en Python para el área comercial.",
        "Pipelines con Airflow.",
    ]


def test_parte_en_oraciones_y_clausulas() -> None:
    bloques = segmentar(
        "Experiencia\nOrquesté 40 microservicios con Kubernetes y Helm; imágenes con Docker. Luego, CI/CD."
    )

    assert [f.texto for f in fragmentos.construir(bloques)] == [
        "Orquesté 40 microservicios con Kubernetes y Helm",
        "imágenes con Docker.",
        "Luego, CI/CD.",
    ]


def test_excluye_datos_personales_de_cualquier_seccion() -> None:
    bloques = segmentar("Perfil\nBackend con Java.\nContacto: +51 987 654 321\nNacida en Lima.")

    assert [f.texto for f in fragmentos.construir(bloques)] == ["Backend con Java."]


def test_la_evidencia_semantica_describe_lo_que_la_persona_hizo() -> None:
    bloques = segmentar(
        "Experiencia\nBackend Engineer — Nube Logística (2021 – actualidad)\nAPIs con Spring Boot.\n\n"
        "Formación\nIngeniería de Sistemas — Universidad Nacional."
    )

    assert [f.texto for f in fragmentos.descriptivos(bloques, HOY)] == ["APIs con Spring Boot."]
    # Para guardar en Laravel se conservan todos.
    assert len(fragmentos.construir(bloques)) == 3


@pytest.mark.parametrize(
    ("texto", "seccion", "dudoso"),
    [
        ("Kubernetes (interés en aprender) y arquitectura en la nube.", SeccionCv.HABILIDADES, True),
        ("Nociones de Java.", SeccionCv.HABILIDADES, True),
        ("Fotografía y montaña.", SeccionCv.INTERESES, True),
        ("Orquesté 40 microservicios con Kubernetes.", SeccionCv.EXPERIENCIA, False),
    ],
)
def test_reconoce_fragmentos_dudosos(texto: str, seccion: SeccionCv, dudoso: bool) -> None:
    assert es_fragmento_dudoso(texto, seccion) is dudoso


def test_un_fragmento_dudoso_nunca_da_coincidencia_por_similitud() -> None:
    requisito = RequisitoInput(id=1, competencia="Computación en la nube", tipo=TipoRequisito.OBLIGATORIO, peso=3)
    interes = EvidenciaSemantica("Arquitectura en la nube (interés).", SeccionCv.INTERESES, 0.95, dudosa=True)

    calificacion = calificar(requisito, None, interes, UmbralesSemanticos(revisar=0.5, coincide=0.6))

    assert (calificacion.razon, calificacion.nivel) == (Razon.SIMILITUD_PARCIAL, NivelCoincidencia.REVISAR)


def test_compara_el_requisito_completo_su_nombre_y_su_descripcion() -> None:
    requisito = RequisitoInput(
        id=1,
        competencia="Mensajería asíncrona",
        sinonimos=["colas"],
        tipo=TipoRequisito.DESEABLE,
        peso=2,
        descripcion="Integración mediante colas de mensajes",
    )

    assert vistas_de_consulta(requisito) == [
        "Mensajería asíncrona (colas). Integración mediante colas de mensajes.",
        "Mensajería asíncrona (colas)",
        "Integración mediante colas de mensajes",
    ]
    sin_descripcion = requisito.model_copy(update={"descripcion": None, "sinonimos": []})
    assert vistas_de_consulta(sin_descripcion) == ["Mensajería asíncrona.", "Mensajería asíncrona"]

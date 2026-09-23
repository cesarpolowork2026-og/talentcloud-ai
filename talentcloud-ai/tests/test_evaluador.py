import math
from collections.abc import Callable, Sequence

import pytest

from talentcloud_ai.contrato.comun import NivelCoincidencia, SeccionCv, TipoRequisito
from talentcloud_ai.contrato.cv import CompetenciaDetectada
from talentcloud_ai.contrato.matching import MatchInput, MatchResult, RequisitoInput
from talentcloud_ai.embeddings.determinista import EmbedderDeterminista
from talentcloud_ai.explicabilidad.motivos import anios_en_texto, motivo, similitud_en_texto
from talentcloud_ai.matching import umbrales
from talentcloud_ai.matching.calificacion import Razon, UmbralesSemanticos, calificar
from talentcloud_ai.matching.evaluador import EvaluadorDeMatching
from talentcloud_ai.matching.semantica import EvidenciaSemantica, texto_de_consulta
from tests.conftest import HOY


class EmbedderDePrueba:
    """Vectores controlados: cada texto se proyecta sobre los temas que menciona (por palabra clave)."""

    def __init__(self, temas: dict[str, list[float]]) -> None:
        self._temas = temas
        self._dimensiones = len(next(iter(temas.values())))

    @property
    def modelo(self) -> str:
        return "prueba"

    @property
    def dimensiones(self) -> int:
        return self._dimensiones

    def codificar(self, textos: Sequence[str]) -> list[list[float]]:
        return [self._vector(t.lower()) for t in textos]

    def _vector(self, texto: str) -> list[float]:
        suma = [0.0] * self._dimensiones
        for clave, vector in self._temas.items():
            if clave in texto:
                suma = [a + b for a, b in zip(suma, vector, strict=True)]
        norma = math.sqrt(sum(v * v for v in suma)) or 1.0
        return [v / norma for v in suma]


def requisito(
    competencia: str,
    anios_minimos: int | None = None,
    tipo: TipoRequisito = TipoRequisito.OBLIGATORIO,
    peso: int = 3,
    id_: int = 1,
    descripcion: str | None = None,
) -> RequisitoInput:
    return RequisitoInput(
        id=id_,
        competencia=competencia,
        sinonimos=[],
        tipo=tipo,
        peso=peso,
        anios_minimos=anios_minimos,
        descripcion=descripcion,
    )


def detectada(
    anios: float | None = None, dudosa: bool = False, seccion: SeccionCv = SeccionCv.EXPERIENCIA
) -> CompetenciaDetectada:
    return CompetenciaDetectada(
        competencia_id=1, nombre="Java", anios=anios, evidencia="…", seccion=seccion, dudosa=dudosa
    )


def parecida(similitud: float) -> EvidenciaSemantica:
    return EvidenciaSemantica("Orquesté microservicios con Helm.", SeccionCv.EXPERIENCIA, similitud)


def entrada(texto_cv: str, *requisitos: RequisitoInput) -> MatchInput:
    return MatchInput.model_validate(
        {
            "version": 1,
            "postulacion_id": 1,
            "vacante": {"titulo": "Backend"},
            "requisitos": [r.model_dump() for r in requisitos],
            "cv": {"id": 1, "texto": texto_cv},
        }
    )


def evaluar_ejemplo(ejemplo: Callable[[str], dict[str, object]]) -> MatchResult:
    return EvaluadorDeMatching(HOY, EmbedderDeterminista()).evaluar(MatchInput.model_validate(ejemplo("match_input")))


# — Evidencia exacta —


@pytest.mark.parametrize(
    ("minimo", "anios", "porcentaje", "nivel"),
    [
        (3, 5.0, 94, NivelCoincidencia.COINCIDE),
        (3, 3.0, 90, NivelCoincidencia.COINCIDE),
        (2, 20.0, umbrales.MAXIMO, NivelCoincidencia.COINCIDE),
        (4, 2.0, 42, NivelCoincidencia.REVISAR),
        # Casi alcanza el mínimo, pero no lo alcanza: nunca "Coincide".
        (3, 2.9, 58, NivelCoincidencia.REVISAR),
        (3, None, umbrales.ANIOS_NO_INDICADOS, NivelCoincidencia.REVISAR),
    ],
)
def test_califica_segun_los_anios_exigidos(
    minimo: int, anios: float | None, porcentaje: int, nivel: NivelCoincidencia
) -> None:
    calificacion = calificar(requisito("Java", minimo), detectada(anios))

    assert (calificacion.porcentaje, calificacion.nivel) == (porcentaje, nivel)


def test_valora_mas_la_evidencia_laboral_que_una_lista_de_habilidades() -> None:
    laboral = calificar(requisito("Java"), detectada(seccion=SeccionCv.EXPERIENCIA))
    lista = calificar(requisito("Java"), detectada(seccion=SeccionCv.HABILIDADES))

    assert laboral.porcentaje == umbrales.BASE_MENCION + umbrales.BONO_EXPERIENCIA_LABORAL
    assert lista.porcentaje == umbrales.BASE_MENCION


def test_una_mencion_dudosa_queda_para_revisar() -> None:
    calificacion = calificar(requisito("Kubernetes", 2), detectada(dudosa=True))
    assert (calificacion.porcentaje, calificacion.nivel) == (20, NivelCoincidencia.REVISAR)


def test_la_evidencia_exacta_manda_sobre_la_semantica() -> None:
    calificacion = calificar(requisito("Java"), detectada(dudosa=True), parecida(0.95))
    assert calificacion.razon is Razon.SOLO_INTERES


# — Evidencia semántica —

UMBRALES = UmbralesSemanticos(revisar=0.4, coincide=0.6)


@pytest.mark.parametrize(
    ("similitud", "razon", "nivel", "porcentaje"),
    [
        (1.0, Razon.SIMILITUD_ALTA, NivelCoincidencia.COINCIDE, umbrales.SEMANTICA_COINCIDE_MAX),
        (0.6, Razon.SIMILITUD_ALTA, NivelCoincidencia.COINCIDE, umbrales.SEMANTICA_COINCIDE_MIN),
        (0.5, Razon.SIMILITUD_PARCIAL, NivelCoincidencia.REVISAR, 40),
        (0.4, Razon.SIMILITUD_PARCIAL, NivelCoincidencia.REVISAR, umbrales.SEMANTICA_REVISAR_MIN),
        (0.39, Razon.SIN_EVIDENCIA, NivelCoincidencia.NO_ENCONTRADO, 0),
    ],
)
def test_califica_la_similitud_segun_los_umbrales(
    similitud: float, razon: Razon, nivel: NivelCoincidencia, porcentaje: int
) -> None:
    calificacion = calificar(requisito("Orquestación de contenedores"), None, parecida(similitud), UMBRALES)

    assert (calificacion.razon, calificacion.nivel, calificacion.porcentaje) == (razon, nivel, porcentaje)


def test_la_similitud_nunca_alcanza_a_una_coincidencia_exacta() -> None:
    semantica = calificar(requisito("Java"), None, parecida(1.0))
    exacta = calificar(requisito("Java"), detectada(seccion=SeccionCv.HABILIDADES))

    assert semantica.porcentaje < exacta.porcentaje


def test_rechaza_umbrales_incoherentes() -> None:
    with pytest.raises(ValueError, match="revisar ≤ coincide"):
        UmbralesSemanticos(revisar=0.7, coincide=0.5)


def test_describe_el_requisito_sin_los_anios() -> None:
    kubernetes = requisito("Kubernetes", anios_minimos=2, descripcion="Operación de clústeres en producción.")
    kubernetes = kubernetes.model_copy(update={"sinonimos": ["k8s"]})

    assert texto_de_consulta(kubernetes) == "Kubernetes (k8s). Operación de clústeres en producción."


def test_encuentra_evidencia_semantica_sin_mencion_literal() -> None:
    embedder = EmbedderDePrueba({"contenedores": [1.0, 0.0], "helm": [1.0, 0.0], "figma": [0.0, 1.0]})
    cv = "Experiencia\nOrquesté microservicios con Helm en producción.\nDiseño de pantallas en Figma."
    orquestacion = requisito("Orquestación de contenedores", peso=4)

    resultado = EvaluadorDeMatching(HOY, embedder).evaluar(entrada(cv, orquestacion))
    item = resultado.requisitos[0]

    assert item.nivel is NivelCoincidencia.COINCIDE
    assert item.evidencia == "Orquesté microservicios con Helm en producción."
    assert item.motivo.startswith("Similitud semántica alta (1,00) con la experiencia laboral")
    assert "no nombra Orquestación de contenedores" in item.motivo


def test_no_usa_el_modelo_si_todos_los_requisitos_tienen_mencion_literal() -> None:
    class SinModelo(EmbedderDeterminista):
        def codificar(self, textos: Sequence[str]) -> list[list[float]]:
            raise AssertionError("no debía llamarse al modelo")

    resultado = EvaluadorDeMatching(HOY, SinModelo()).evaluar(
        entrada("Experiencia\nServicios con Java.", requisito("Java"))
    )

    assert resultado.requisitos[0].nivel is NivelCoincidencia.COINCIDE


def test_no_compara_con_datos_personales() -> None:
    embedder = EmbedderDePrueba({"lima": [1.0, 0.0], "java": [0.0, 1.0]})
    cv = "Ana Pérez\nLima\n\nDatos personales\nNacida en Lima.\n\nExperiencia\nServicios con Java."

    diagnostico = EvaluadorDeMatching(HOY, embedder).diagnosticar(entrada(cv, requisito("Vivir en Lima")))[0]

    assert diagnostico.semantica is not None
    assert "Lima" not in diagnostico.semantica.texto
    assert diagnostico.calificacion.nivel is NivelCoincidencia.NO_ENCONTRADO


# — Motivos y resumen —


def test_escribe_cifras_en_espanol() -> None:
    assert [anios_en_texto(a) for a in (1.0, 1.5, 5.0, 6.7)] == ["1 año", "1,5 años", "5 años", "6,7 años"]
    assert similitud_en_texto(0.6234) == "0,62"


@pytest.mark.parametrize(
    ("minimo", "anios", "seccion", "esperado"),
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
def test_explica_la_evidencia_exacta_en_lenguaje_llano(
    minimo: int | None, anios: float | None, seccion: SeccionCv, esperado: str
) -> None:
    requisito_java = requisito("Java", minimo)
    java = detectada(anios, seccion=seccion)

    assert motivo(calificar(requisito_java, java), requisito_java, java) == esperado


def test_explica_la_similitud_parcial() -> None:
    orquestacion = requisito("Orquestación de contenedores")
    calificacion = calificar(orquestacion, None, parecida(0.5), UMBRALES)

    assert motivo(calificacion, orquestacion, None) == (
        "Similitud semántica parcial (0,50) con la experiencia laboral: "
        "puede estar relacionado con Orquestación de contenedores; conviene revisarlo."
    )


# — Evaluación completa: el ejemplo del contrato es la vacante "Desarrollador Backend" y el CV de Elena —


def test_evalua_a_elena_como_en_el_prototipo(ejemplo: Callable[[str], dict[str, object]]) -> None:
    resultado = evaluar_ejemplo(ejemplo)
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
        "Coincide en 4 de 6 requisitos (Java, Spring Boot, PostgreSQL y Docker). "
        "Para revisar: Kubernetes. Sin evidencia: Certificación AWS."
    )


def test_senala_siempre_el_requisito_obligatorio_sin_evidencia() -> None:
    obligatorio = requisito("Terraform", tipo=TipoRequisito.OBLIGATORIO, id_=99)
    resultado = EvaluadorDeMatching(HOY, EmbedderDeterminista()).evaluar(entrada("Experiencia\nJava.", obligatorio))

    assert resultado.requisitos[0].motivo == (
        "No se encontró evidencia de Terraform en el CV. Es un requisito obligatorio."
    )
    assert resultado.resumen == "Coincide en 0 de 1 requisitos. Sin evidencia: Terraform (obligatorio)."


def test_pondera_el_puntaje_global_por_peso(ejemplo: Callable[[str], dict[str, object]]) -> None:
    resultado = evaluar_ejemplo(ejemplo)
    datos = MatchInput.model_validate(ejemplo("match_input"))
    pesos = [r.peso for r in datos.requisitos]
    esperado = sum(p * m.porcentaje for p, m in zip(pesos, resultado.requisitos, strict=True)) / sum(pesos)

    assert resultado.score == round(esperado, 2)


def test_una_vacante_sin_requisitos_da_cero() -> None:
    resultado = EvaluadorDeMatching(HOY, EmbedderDeterminista()).evaluar(entrada("Experiencia\nJava."))

    assert (resultado.score, resultado.requisitos, resultado.resumen) == (0.0, [], None)

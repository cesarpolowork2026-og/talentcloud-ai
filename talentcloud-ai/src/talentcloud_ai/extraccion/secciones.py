"""Segmentación del CV en secciones a partir de sus encabezados ("Experiencia", "Habilidades"…)."""

import re
from dataclasses import dataclass, field

from talentcloud_ai.contrato.comun import SeccionCv
from talentcloud_ai.extraccion.texto import lineas_con_vineta, normalizar

# Un encabezado es una línea corta que coincide por completo con uno de estos patrones (normalizados).
_ENCABEZADOS: dict[SeccionCv, str] = {
    SeccionCv.PERFIL: r"perfil( profesional)?|resumen( profesional)?|sobre mi|acerca de mi|objetivo( profesional)?",
    SeccionCv.EXPERIENCIA: r"experiencia( laboral| profesional)?|trayectoria( profesional| laboral)?|historial laboral",
    SeccionCv.EDUCACION: r"educacion|formacion( academica| profesional)?|estudios",
    SeccionCv.HABILIDADES: (
        r"habilidades( tecnicas| blandas)?|competencias( tecnicas)?|conocimientos( tecnicos)?|skills"
        r"|tecnologias( y herramientas)?|herramientas( y tecnologias)?|habilidades y herramientas"
        r"|stack( tecnologico)?|aptitudes"
    ),
    SeccionCv.CERTIFICACIONES: r"certificaciones( y cursos)?|certificados|cursos( y certificaciones)?",
    SeccionCv.IDIOMAS: r"idiomas|lenguas",
    SeccionCv.PROYECTOS: r"proyectos( destacados| personales| academicos)?( y (personales|academicos|profesionales))?",
    SeccionCv.INTERESES: r"intereses|aficiones|hobbies",
    SeccionCv.DATOS_PERSONALES: r"datos personales|informacion personal|(datos de )?contacto",
    SeccionCv.REFERENCIAS: r"referencias( laborales| personales)?",
}
_PATRONES = {seccion: re.compile(rf"(?:{patron})") for seccion, patron in _ENCABEZADOS.items()}
_MAX_LARGO_ENCABEZADO = 40
# CV sin encabezados reconocibles: solo las primeras líneas cortas son el encabezado (nombre, contacto).
_MAX_LINEAS_DE_ENCABEZADO = 3
_MAX_LARGO_LINEA_DE_ENCABEZADO = 80


@dataclass
class BloqueDeSeccion:
    nombre: SeccionCv
    titulo: str | None
    orden: int
    lineas: list[str] = field(default_factory=list)
    # Posiciones (en `lineas`) de las que empezaban con viñeta: logros y tareas, no títulos de puesto.
    vinetas: set[int] = field(default_factory=set)

    def agregar(self, linea: str, es_vineta: bool) -> None:
        if es_vineta:
            self.vinetas.add(len(self.lineas))
        self.lineas.append(linea)

    @property
    def texto(self) -> str:
        return "\n".join(self.lineas)


def tipo_de_encabezado(linea: str) -> SeccionCv | None:
    """La sección que abre la línea, o None si no es un encabezado."""
    if len(linea) > _MAX_LARGO_ENCABEZADO:
        return None
    candidato = normalizar(linea).rstrip(":").strip()
    for seccion, patron in _PATRONES.items():
        if patron.fullmatch(candidato):
            return seccion
    return None


def segmentar(texto: str) -> list[BloqueDeSeccion]:
    """Divide el CV en secciones. Lo anterior al primer encabezado es el encabezado del CV (nombre, contacto)."""
    bloques: list[BloqueDeSeccion] = []
    actual = BloqueDeSeccion(SeccionCv.ENCABEZADO, None, 0)

    todas = lineas_con_vineta(texto)
    for linea, es_vineta in todas:
        seccion = tipo_de_encabezado(linea)
        if seccion is None:
            actual.agregar(linea, es_vineta)
            continue
        if actual.lineas:
            bloques.append(actual)
        actual = BloqueDeSeccion(seccion, linea.rstrip(":"), len(bloques))

    if actual.lineas:
        bloques.append(actual)

    if len(bloques) == 1 and bloques[0].nombre is SeccionCv.ENCABEZADO:
        bloques = _sin_encabezados(todas)

    # Reenumera por si hubo encabezados sin contenido.
    for orden, bloque in enumerate(bloques):
        bloque.orden = orden
    return bloques


def _sin_encabezados(todas: list[tuple[str, bool]]) -> list[BloqueDeSeccion]:
    """Sin ningún encabezado de sección, el resto del CV no puede quedar como "encabezado" (se excluiría entero)."""
    cantidad = 0
    while (
        cantidad < min(_MAX_LINEAS_DE_ENCABEZADO, len(todas) - 1)
        and len(todas[cantidad][0]) <= _MAX_LARGO_LINEA_DE_ENCABEZADO
    ):
        cantidad += 1

    bloques: list[BloqueDeSeccion] = []
    if cantidad:
        bloques.append(_bloque(SeccionCv.ENCABEZADO, 0, todas[:cantidad]))
    return [*bloques, _bloque(SeccionCv.OTROS, len(bloques), todas[cantidad:])]


def _bloque(nombre: SeccionCv, orden: int, lineas: list[tuple[str, bool]]) -> BloqueDeSeccion:
    bloque = BloqueDeSeccion(nombre, None, orden)
    for linea, es_vineta in lineas:
        bloque.agregar(linea, es_vineta)
    return bloque

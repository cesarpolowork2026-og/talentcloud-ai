"""Segmentación del CV en secciones a partir de sus encabezados ("Experiencia", "Habilidades"…)."""

import re
from dataclasses import dataclass, field

from talentcloud_ai.contrato.comun import SeccionCv
from talentcloud_ai.extraccion.texto import lineas, normalizar

# Un encabezado es una línea corta que coincide por completo con uno de estos patrones (normalizados).
_ENCABEZADOS: dict[SeccionCv, str] = {
    SeccionCv.PERFIL: r"perfil( profesional)?|resumen( profesional)?|sobre mi|acerca de mi|objetivo( profesional)?",
    SeccionCv.EXPERIENCIA: r"experiencia( laboral| profesional)?|trayectoria( profesional| laboral)?|historial laboral",
    SeccionCv.EDUCACION: r"educacion|formacion( academica| profesional)?|estudios",
    SeccionCv.HABILIDADES: (
        r"habilidades( tecnicas)?|competencias( tecnicas)?|conocimientos( tecnicos)?|skills|tecnologias"
        r"|herramientas|stack( tecnologico)?|aptitudes"
    ),
    SeccionCv.CERTIFICACIONES: r"certificaciones( y cursos)?|certificados|cursos( y certificaciones)?",
    SeccionCv.IDIOMAS: r"idiomas|lenguas",
    SeccionCv.PROYECTOS: r"proyectos( destacados| personales)?",
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

    for linea in lineas(texto):
        seccion = tipo_de_encabezado(linea)
        if seccion is None:
            actual.lineas.append(linea)
            continue
        if actual.lineas:
            bloques.append(actual)
        actual = BloqueDeSeccion(seccion, linea.rstrip(":"), len(bloques))

    if actual.lineas:
        bloques.append(actual)

    if len(bloques) == 1 and bloques[0].nombre is SeccionCv.ENCABEZADO:
        bloques = _sin_encabezados(bloques[0].lineas)

    # Reenumera por si hubo encabezados sin contenido.
    for orden, bloque in enumerate(bloques):
        bloque.orden = orden
    return bloques


def _sin_encabezados(todas: list[str]) -> list[BloqueDeSeccion]:
    """Sin ningún encabezado de sección, el resto del CV no puede quedar como "encabezado" (se excluiría entero)."""
    cantidad = 0
    while (
        cantidad < min(_MAX_LINEAS_DE_ENCABEZADO, len(todas) - 1)
        and len(todas[cantidad]) <= _MAX_LARGO_LINEA_DE_ENCABEZADO
    ):
        cantidad += 1
    bloques = [BloqueDeSeccion(SeccionCv.ENCABEZADO, None, 0, todas[:cantidad])] if cantidad else []
    return [*bloques, BloqueDeSeccion(SeccionCv.OTROS, None, len(bloques), todas[cantidad:])]

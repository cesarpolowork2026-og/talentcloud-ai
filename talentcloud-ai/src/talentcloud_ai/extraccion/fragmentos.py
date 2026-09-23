"""Fragmentos del CV: las unidades que se comparan por similitud semántica y que se guardan con su embedding.

Un fragmento es una oración, o una cláusula si la oración las separa con ";": así una línea como
"Orquesté 40 microservicios con Kubernetes; imágenes con Docker…" no mezcla dos temas en un solo vector.
Nunca incluye el encabezado, los datos personales ni las referencias, ni líneas con datos de contacto
o rasgos personales: el matching no usa nombre, género, edad, foto ni lugar de nacimiento.
"""

import re
from collections.abc import Callable, Iterable, Iterator
from datetime import date

from talentcloud_ai.contrato.comun import SeccionCv
from talentcloud_ai.contrato.cv import Fragmento
from talentcloud_ai.extraccion.experiencia import leer_puesto
from talentcloud_ai.extraccion.privacidad import es_dato_personal
from talentcloud_ai.extraccion.secciones import BloqueDeSeccion
from talentcloud_ai.extraccion.texto import oraciones

SECCIONES_PERSONALES = frozenset({SeccionCv.ENCABEZADO, SeccionCv.DATOS_PERSONALES, SeccionCv.REFERENCIAS})
MAX_CARACTERES = 500
_MIN_CARACTERES = 3
_PUNTO_Y_COMA = re.compile(r"\s*;\s*")


def unidades(linea: str) -> Iterator[str]:
    """Oraciones y cláusulas de una línea, sin superar MAX_CARACTERES."""
    for oracion in oraciones(linea):
        for clausula in _PUNTO_Y_COMA.split(oracion):
            texto = clausula.strip(" ;")
            if len(texto) >= _MIN_CARACTERES:
                yield from _cortar(texto)


def _cortar(texto: str) -> Iterator[str]:
    """Una unidad más larga que el máximo se corta por palabras."""
    while len(texto) > MAX_CARACTERES:
        corte = texto.rfind(" ", 0, MAX_CARACTERES)
        corte = corte if corte > 0 else MAX_CARACTERES
        yield texto[:corte]
        texto = texto[corte:].strip()
    if texto:
        yield texto


def construir(
    bloques: Iterable[BloqueDeSeccion], excluir: Callable[[BloqueDeSeccion, str], bool] | None = None
) -> list[Fragmento]:
    fragmentos: list[Fragmento] = []
    for bloque in bloques:
        if bloque.nombre in SECCIONES_PERSONALES:
            continue
        for linea in bloque.lineas:
            if es_dato_personal(linea) or (excluir is not None and excluir(bloque, linea)):
                continue
            for texto in unidades(linea):
                fragmentos.append(Fragmento(seccion=bloque.nombre, orden=len(fragmentos), texto=texto))
    return fragmentos


def descriptivos(bloques: Iterable[BloqueDeSeccion], hoy: date) -> list[Fragmento]:
    """Solo los fragmentos que describen lo que la persona hizo: la evidencia semántica.

    Se dejan fuera las líneas de puesto ("Backend Engineer — Nube Logística (2021 – actualidad)") y la
    formación: son títulos genéricos que se parecen a casi cualquier requisito sin demostrarlo. Siguen
    contando para la coincidencia exacta (años por puesto) y se guardan en Laravel como el resto.
    """
    return construir(
        bloques,
        excluir=lambda bloque, linea: bloque.nombre is SeccionCv.EDUCACION or leer_puesto(linea, hoy) is not None,
    )

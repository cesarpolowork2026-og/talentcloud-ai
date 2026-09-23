"""Fragmentos del CV para embeddings (día 13): uno por línea, partiendo las líneas largas por oraciones.

Nunca incluye el encabezado (nombre, contacto), los datos personales ni las referencias: el matching
no debe usar nombre, género, edad, foto ni lugar de nacimiento.
"""

from collections.abc import Iterable

from talentcloud_ai.contrato.comun import SeccionCv
from talentcloud_ai.contrato.cv import Fragmento
from talentcloud_ai.extraccion.secciones import BloqueDeSeccion
from talentcloud_ai.extraccion.texto import oraciones

SECCIONES_PERSONALES = frozenset({SeccionCv.ENCABEZADO, SeccionCv.DATOS_PERSONALES, SeccionCv.REFERENCIAS})
MAX_CARACTERES = 500


def _partir(linea: str) -> list[str]:
    if len(linea) <= MAX_CARACTERES:
        return [linea]
    partes: list[str] = []
    actual = ""
    for oracion in oraciones(linea):
        if actual and len(actual) + 1 + len(oracion) > MAX_CARACTERES:
            partes.append(actual)
            actual = ""
        actual = f"{actual} {oracion}".strip()
        # Una oración sola más larga que el máximo se corta por palabras.
        while len(actual) > MAX_CARACTERES:
            corte = actual.rfind(" ", 0, MAX_CARACTERES)
            corte = corte if corte > 0 else MAX_CARACTERES
            partes.append(actual[:corte])
            actual = actual[corte:].strip()
    if actual:
        partes.append(actual)
    return partes


def construir(bloques: Iterable[BloqueDeSeccion]) -> list[Fragmento]:
    fragmentos: list[Fragmento] = []
    for bloque in bloques:
        if bloque.nombre in SECCIONES_PERSONALES:
            continue
        for linea in bloque.lineas:
            for texto in _partir(linea):
                fragmentos.append(Fragmento(seccion=bloque.nombre, orden=len(fragmentos), texto=texto))
    return fragmentos

"""Certificaciones: las líneas de la sección "Certificaciones" y las menciones sueltas en el resto del CV."""

import re
from collections.abc import Iterable

from talentcloud_ai.contrato.comun import SeccionCv
from talentcloud_ai.contrato.cv import Certificacion
from talentcloud_ai.extraccion.secciones import BloqueDeSeccion
from talentcloud_ai.extraccion.texto import normalizar, oraciones, recortar

_SECCIONES_EXCLUIDAS = {SeccionCv.DATOS_PERSONALES, SeccionCv.REFERENCIAS}
# "Obtuve la certificación AWS Solutions Architect – Associate." → "AWS Solutions Architect – Associate"
_TRAS_LA_PALABRA = re.compile(
    r"(?:certificaci[oó]n(?:es)?|certificad[oa])\s+(?:(?:en|de|como)\s+)?(?P<nombre>[^.;,()\n]{2,120})",
    re.IGNORECASE,
)
# "AWS Certified Developer – Associate (2024)." → la frase completa es el nombre.
_EN_INGLES = re.compile(r"\b(?:certified|certification|certificate)\b", re.IGNORECASE)
_ANIO_FINAL = re.compile(r"\s*[(\[]?\s*(?:19|20)\d{2}\s*[)\]]?\s*[.;]?\s*$")
_BORDES = " \t.;:,-–—"


def _limpiar_nombre(texto: str) -> str:
    return _ANIO_FINAL.sub("", texto).strip(_BORDES)


def extraer(bloques: Iterable[BloqueDeSeccion]) -> list[Certificacion]:
    """Certificaciones del CV, sin duplicados y con su fragmento de origen."""
    encontradas: dict[str, Certificacion] = {}

    def agregar(nombre: str, evidencia: str) -> None:
        nombre = _limpiar_nombre(nombre)
        clave = normalizar(nombre)
        if clave and clave not in encontradas:
            encontradas[clave] = Certificacion(nombre=nombre, evidencia=recortar(evidencia))

    for bloque in bloques:
        if bloque.nombre in _SECCIONES_EXCLUIDAS:
            continue
        for linea in bloque.lineas:
            if bloque.nombre is SeccionCv.CERTIFICACIONES:
                agregar(linea, linea)
                continue
            for oracion in oraciones(linea):
                if mencion := _TRAS_LA_PALABRA.search(oracion):
                    agregar(mencion["nombre"], oracion)
                elif _EN_INGLES.search(oracion):
                    agregar(oracion, oracion)

    return list(encontradas.values())

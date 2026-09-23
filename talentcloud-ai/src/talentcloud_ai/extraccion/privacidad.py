"""Datos personales que no deben llegar al matching ni a los embeddings.

El matching no usa nombre, género, edad, foto, lugar de nacimiento ni datos de contacto: además de
excluir el encabezado y la sección de datos personales, se descartan las líneas que los contienen
aunque aparezcan en otra sección.
"""

import re

from talentcloud_ai.extraccion.texto import normalizar

_CORREO = re.compile(r"[\w.+-]+@[\w-]+\.[\w.]+")
_URL = re.compile(r"https?://|www\.|linkedin\.com|github\.com/")
# Rasgos personales (sobre texto normalizado: sin tildes y en minúsculas). Las palabras ambiguas
# ("genero reportes", "bajo la direccion de…") solo cuentan como etiqueta: "Género:", "Dirección:".
_RASGOS = re.compile(
    r"\b(fecha de nacimiento|lugar de nacimiento|nacid[oa] (en|el)|\d{2} anos de edad|documento de identidad)\b"
    r"|\b(edad|dni|pasaporte|estado civil|nacionalidad|sexo|genero|direccion|domicilio|telefono|celular|whatsapp)\s*:"
)
# Un teléfono tiene 8 o más dígitos; un periodo "2015-2021" también, así que se descarta aparte.
_TELEFONO = re.compile(r"\+?\d[\d\s().-]{6,}\d")
_PERIODO = re.compile(r"^(19|20)\d{2}\s*[-–—]\s*(19|20)\d{2}$")
_MIN_DIGITOS_TELEFONO = 8


def es_dato_personal(linea: str) -> bool:
    """La línea contiene contacto o rasgos personales y se excluye del matching."""
    if _CORREO.search(linea) or _URL.search(linea.lower()):
        return True
    if _RASGOS.search(normalizar(linea)):
        return True
    return any(_parece_telefono(m.group()) for m in _TELEFONO.finditer(linea))


def _parece_telefono(candidato: str) -> bool:
    candidato = candidato.strip()
    digitos = sum(c.isdigit() for c in candidato)
    return digitos >= _MIN_DIGITOS_TELEFONO and not _PERIODO.match(candidato)

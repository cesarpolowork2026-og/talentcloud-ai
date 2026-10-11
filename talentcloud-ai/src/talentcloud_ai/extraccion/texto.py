"""Utilidades de texto: normalización para comparar y división en líneas y oraciones.

Se compara siempre sobre texto normalizado (sin tildes, en minúsculas), pero la evidencia
que se devuelve es el texto original, tal como está en el CV.
"""

import re
import unicodedata

_VINETAS = re.compile(r"^[\s•·▪●◦*\-–—]+")
# Una línea que empieza con viñeta es un elemento de lista (un logro, una tarea), no un título.
_EMPIEZA_CON_VINETA = re.compile(r"^\s*[•·▪●◦*\-–—]")
_ESPACIOS = re.compile(r"\s+")
# Fin de oración: . ; ! ? seguidos de espacio y una mayúscula o dígito (no parte "Node.js" ni "S.A.").
_FIN_DE_ORACION = re.compile(r"(?<=[.;!?])\s+(?=[A-ZÁÉÍÓÚÑ0-9¿¡])")

MAX_EVIDENCIA = 300


def plegar(texto: str) -> str:
    """Quita tildes y diacríticos conservando mayúsculas ("Diseñé" → "Disene")."""
    descompuesto = unicodedata.normalize("NFKD", texto)
    return "".join(c for c in descompuesto if not unicodedata.combining(c))


def normalizar(texto: str) -> str:
    """Forma de comparación: sin tildes, minúsculas y espacios simples."""
    return _ESPACIOS.sub(" ", plegar(texto).lower()).strip()


def plegar_alineado(texto: str) -> str:
    """Como `plegar`, pero carácter a carácter: conserva la longitud, así que una posición
    encontrada en el resultado sirve tal cual en el texto original."""
    return "".join(" " if c.isspace() else (plegar(c) or c)[:1] for c in texto)


def normalizar_alineado(texto: str) -> str:
    """Como `normalizar` (sin colapsar espacios), conservando la longitud del original."""
    return "".join(c.lower()[:1] for c in plegar_alineado(texto))


def limpiar_linea(linea: str) -> str:
    """Quita viñetas y espacios sobrantes de una línea."""
    return _ESPACIOS.sub(" ", _VINETAS.sub("", linea)).strip()


def lineas(texto: str) -> list[str]:
    """Líneas no vacías del texto, ya limpias."""
    return [limpia for limpia, _ in lineas_con_vineta(texto)]


def lineas_con_vineta(texto: str) -> list[tuple[str, bool]]:
    """Líneas no vacías del texto, ya limpias, indicando si empezaban con una viñeta."""
    return [
        (limpia, _EMPIEZA_CON_VINETA.match(linea) is not None)
        for linea in texto.splitlines()
        if (limpia := limpiar_linea(linea))
    ]


def oraciones(linea: str) -> list[str]:
    """Oraciones de una línea."""
    return [o.strip() for o in _FIN_DE_ORACION.split(linea) if o.strip()]


def recortar(texto: str, maximo: int = MAX_EVIDENCIA) -> str:
    """Recorta la evidencia sin partir palabras."""
    if len(texto) <= maximo:
        return texto
    return texto[:maximo].rsplit(" ", 1)[0].rstrip(" ,;:") + "…"


def contiene_termino(texto_normalizado: str, termino_normalizado: str) -> bool:
    """Busca un término como palabra completa ("sql" no coincide dentro de "postgresql")."""
    patron = r"(?<![a-z0-9])" + re.escape(termino_normalizado) + r"(?![a-z0-9])"
    return re.search(patron, texto_normalizado) is not None

"""Detección de competencias del catálogo (con sinónimos: "Postgres" → PostgreSQL) en las oraciones del CV."""

import re
from collections.abc import Sequence
from dataclasses import dataclass

from talentcloud_ai.contrato.comun import SeccionCv
from talentcloud_ai.contrato.cv import CompetenciaCatalogo
from talentcloud_ai.extraccion.texto import normalizar, normalizar_alineado, plegar, plegar_alineado

# Menciones que no acreditan experiencia: "Kubernetes (interés en aprender)", "nociones de Java".
_MARCADORES_DUDOSOS = re.compile(
    r"interes en|quiero aprender|me gustaria aprender|aprendiendo|en formacion|nociones|basicos?\b"
    r"|conocimientos basicos|curso introductorio|familiarizad[oa]"
)
# Los términos cortos ("Go", "TS", "ML") se buscan respetando mayúsculas para no confundirlos con palabras comunes.
_LARGO_SENSIBLE_A_MAYUSCULAS = 3


@dataclass(frozen=True)
class Termino:
    patron: re.Pattern[str]
    sensible_a_mayusculas: bool


@dataclass(frozen=True)
class CompetenciaBuscada:
    id: int
    nombre: str
    terminos: tuple[Termino, ...]


@dataclass(frozen=True)
class Encontrada:
    competencia_id: int
    nombre: str
    posicion: int  # en la oración normalizada (alineada con la original)


def _termino(texto: str) -> Termino | None:
    plegado = plegar(texto).strip()
    if not plegado:
        return None
    sensible = len(plegado) <= _LARGO_SENSIBLE_A_MAYUSCULAS
    forma = plegado if sensible else normalizar(plegado)
    patron = re.compile(r"(?<![A-Za-z0-9])" + re.escape(forma).replace(r"\ ", r"\s+") + r"(?![A-Za-z0-9])")
    return Termino(patron, sensible)


class DetectorDeCompetencias:
    """Busca las competencias de un catálogo como palabras completas, sin tildes y con sus sinónimos."""

    def __init__(self, catalogo: Sequence[CompetenciaCatalogo]) -> None:
        self._competencias: list[CompetenciaBuscada] = []
        for competencia in catalogo:
            formas = dict.fromkeys(t for t in (_termino(x) for x in (competencia.nombre, *competencia.sinonimos)) if t)
            if formas:
                self._competencias.append(CompetenciaBuscada(competencia.id, competencia.nombre, tuple(formas)))

    def buscar(self, oracion: str) -> list[Encontrada]:
        """Competencias mencionadas en una oración, con la posición de su primera mención."""
        plegada = plegar_alineado(oracion)
        normalizada = normalizar_alineado(oracion)
        encontradas: list[Encontrada] = []
        for competencia in self._competencias:
            posiciones = [
                coincidencia.start()
                for termino in competencia.terminos
                if (coincidencia := termino.patron.search(plegada if termino.sensible_a_mayusculas else normalizada))
            ]
            if posiciones:
                encontradas.append(Encontrada(competencia.id, competencia.nombre, min(posiciones)))
        return encontradas


_SEPARADOR_DE_CLAUSULAS = re.compile(r"[,;]|\s+y\s+|\s+e\s+")


def clausula(oracion_normalizada: str, posicion: int) -> str:
    """La cláusula (entre comas, punto y coma o "y") que contiene la posición."""
    inicio = 0
    for separador in _SEPARADOR_DE_CLAUSULAS.finditer(oracion_normalizada):
        if separador.start() >= posicion:
            return oracion_normalizada[inicio : separador.start()]
        inicio = separador.end()
    return oracion_normalizada[inicio:]


def es_mencion_dudosa(oracion_normalizada: str, posicion: int, seccion: SeccionCv) -> bool:
    """La mención no acredita experiencia: está en "Intereses" o su cláusula dice "interés en aprender", "nociones"…

    En "Java, Python (básico)" solo Python es dudosa.
    """
    if seccion is SeccionCv.INTERESES:
        return True
    return _MARCADORES_DUDOSOS.search(clausula(oracion_normalizada, posicion)) is not None


def es_fragmento_dudoso(texto: str, seccion: SeccionCv) -> bool:
    """Un fragmento entero (una cláusula) que habla de interés o nivel básico, no de experiencia."""
    return seccion is SeccionCv.INTERESES or _MARCADORES_DUDOSOS.search(normalizar(texto)) is not None

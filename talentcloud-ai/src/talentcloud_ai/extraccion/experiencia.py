"""Experiencia: años declarados en el texto y puestos con su periodo ("Backend Developer — Acme (2020 – 2022)")."""

import re
from collections.abc import Collection, Iterable, Sequence
from dataclasses import dataclass
from datetime import date

from talentcloud_ai.extraccion.texto import normalizar_alineado

# — Años declarados: "5 años de experiencia con Java", "un año y medio", "6 años construyendo…" —

_NUMEROS = {
    "un": 1, "uno": 1, "una": 1, "dos": 2, "tres": 3, "cuatro": 4, "cinco": 5, "seis": 6, "siete": 7,
    "ocho": 8, "nueve": 9, "diez": 10, "once": 11, "doce": 12, "quince": 15, "veinte": 20,
}  # fmt: skip
_NUMERO = r"\d{1,2}(?:[.,]\d)?|" + "|".join(sorted(_NUMEROS, key=len, reverse=True))
# Sobre texto normalizado: "años" queda como "anos".
_ANIOS = re.compile(rf"(?<![a-z0-9])(?P<n>{_NUMERO})\s*\+?\s*anos?(?P<medio>\s+y\s+medio)?(?![a-z0-9])")
# Qué sigue a "N años" para que hable de experiencia y no de otra cosa ("una carrera de 5 años").
_CONTEXTO_DE_EXPERIENCIA = re.compile(
    r"\s*(?:de\s+)?(?:experiencia|trabajando|construyendo|desarrollando|programando|usando|utilizando|operando"
    r"|liderando|disenando|con|en|como)\b"
)
_MAX_ANIOS_CREIBLES = 50


@dataclass(frozen=True)
class AniosDeclarados:
    inicio: int  # posición en la oración normalizada
    fin: int
    anios: float


def anios_declarados(oracion_normalizada: str) -> list[AniosDeclarados]:
    """Menciones de años de experiencia en una oración ya normalizada."""
    habla_de_experiencia = "experiencia" in oracion_normalizada
    encontrados: list[AniosDeclarados] = []
    for coincidencia in _ANIOS.finditer(oracion_normalizada):
        siguiente = oracion_normalizada[coincidencia.end() :]
        if not habla_de_experiencia and not _CONTEXTO_DE_EXPERIENCIA.match(siguiente):
            continue
        valor = coincidencia["n"]
        anios = float(_NUMEROS[valor]) if valor in _NUMEROS else float(valor.replace(",", "."))
        if coincidencia["medio"]:
            anios += 0.5
        if 0 < anios <= _MAX_ANIOS_CREIBLES:
            encontrados.append(AniosDeclarados(coincidencia.start(), coincidencia.end(), anios))
    return encontrados


def anios_cerca_de(oracion_normalizada: str, posicion: int) -> float | None:
    """Años declarados que corresponden a la mención en `posicion`: la cifra más cercana, prefiriendo la anterior.

    "5 años de experiencia en datos y 6 años con Java" → para Java, 6.
    """
    declarados = anios_declarados(oracion_normalizada)
    anteriores = [d for d in declarados if d.fin <= posicion]
    if anteriores:
        return anteriores[-1].anios
    posteriores = [d for d in declarados if d.inicio >= posicion]
    return posteriores[0].anios if posteriores else None


# — Puestos con periodo —

_MESES = {
    "ene": 1, "jan": 1, "feb": 2, "mar": 3, "abr": 4, "apr": 4, "may": 5, "jun": 6, "jul": 7, "ago": 8,
    "aug": 8, "sep": 9, "set": 9, "oct": 10, "nov": 11, "dic": 12, "dec": 12,
}  # fmt: skip
_ACTUAL = r"actualidad|presente|actual|la fecha|hoy|present|now"


def _mes(grupo: str) -> str:
    return rf"(?:(?P<{grupo}>{'|'.join(_MESES)})[a-z]*\.?\s+(?:de\s+)?)?"


_PERIODO = re.compile(
    rf"\(?\s*{_mes('mes_desde')}(?P<desde>(?:19|20)\d{{2}})"
    rf"(?:\s*(?:-|–|—|a|al|hasta)\s*{_mes('mes_hasta')}(?P<hasta>(?:19|20)\d{{2}}|{_ACTUAL}))?\s*\)?"
)
# Lo que muchos CV escriben después del periodo y no cambia sus fechas: la duración ("(4 meses)",
# "· 1 año y 2 meses", como en LinkedIn) o el estado de un estudio ("(previsto)", "(en curso)").
_DURACION = rf"(?:{_NUMERO})\s+(?:anos?|mes(?:es)?)"
_TRAS_EL_PERIODO = re.compile(
    rf"\s*[·•|,-]?\s*\(?\s*(?:{_DURACION}(?:\s*(?:y|,)\s*{_DURACION})?|previst[oa]|en curso)\s*\)?[\s.]*$"
)
# Separadores entre cargo y organización, por prioridad ("Cargo — Empresa" antes que "Cargo en Empresa").
_SEPARADORES = (re.compile(r"\s+[—–|@-]\s+"), re.compile(r",\s+"), re.compile(r"\s+(?:en|at)\s+"))
_BORDES = " \t()[]|,.;:—–-·•"
_MAX_LARGO_CARGO = 160


@dataclass(frozen=True)
class Periodo:
    """Intervalo en años decimales: [desde, hasta)."""

    desde: float
    hasta: float

    @property
    def anios(self) -> float:
        return max(0.0, self.hasta - self.desde)


@dataclass(frozen=True)
class PuestoLeido:
    cargo: str
    organizacion: str | None
    desde: int
    hasta: int | None
    periodo: Periodo


def _decimal(anio: int, mes: int | None) -> float:
    return anio + ((mes or 1) - 1) / 12


def _decimal_de_fecha(fecha: date) -> float:
    return fecha.year + (fecha.month - 1) / 12 + (fecha.day - 1) / 365


def leer_puesto(linea: str, hoy: date) -> PuestoLeido | None:
    """Lee una línea de puesto con su periodo, al principio o al final de la línea; None si no lo es."""
    if len(linea) > _MAX_LARGO_CARGO:
        return None
    normal = normalizar_alineado(linea)
    # Lo que sigue al periodo y no es parte de él ("(4 meses)") no impide reconocerlo al final de la línea.
    sufijo = _TRAS_EL_PERIODO.search(normal)
    fin_del_periodo = sufijo.start() if sufijo else len(normal)
    periodo = next(
        (
            m
            for m in _PERIODO.finditer(normal, 0, fin_del_periodo)
            if m.start() == 0 or m.end() >= len(normal[:fin_del_periodo].rstrip(". "))
        ),
        None,
    )
    if periodo is None:
        return None

    desde = int(periodo["desde"])
    mes_desde = _MESES.get(periodo["mes_desde"] or "")
    texto_hasta = periodo["hasta"]
    if texto_hasta is None:  # un solo año: "(2023)"
        hasta: int | None = desde
        fin = float(desde + 1)
    elif texto_hasta.isdigit():
        hasta = int(texto_hasta)
        mes_hasta = _MESES.get(periodo["mes_hasta"] or "")
        # Un mes final cuenta entero: "Mar. 2025 – Jul. 2025" son cinco meses, como los cuenta el CV.
        fin = _decimal(hasta, mes_hasta) + 1 / 12 if mes_hasta else float(hasta)
    else:
        hasta = None
        fin = _decimal_de_fecha(hoy)

    inicio = _decimal(desde, mes_desde)
    if fin < inicio or desde > hoy.year:
        return None

    descripcion = (linea[: periodo.start()] + " " + linea[periodo.end() : fin_del_periodo]).strip(_BORDES)
    if not descripcion:
        return None

    cargo, organizacion = _cargo_y_organizacion(descripcion)
    return PuestoLeido(cargo, organizacion, desde, hasta, Periodo(inicio, fin))


def puestos_del_bloque(lineas: Sequence[str], vinetas: Collection[int], hoy: date) -> dict[int, PuestoLeido]:
    """Los puestos de un bloque del CV, por posición de la línea.

    Una viñeta que termina en un año ("• En 2024 superé la cuota" escrito como "• Superé la cuota en 2024")
    es un logro, no un puesto: las viñetas solo cuentan si el bloque no tiene ningún puesto fuera de una
    (hay CV que escriben también los puestos como viñetas).
    """
    leidos = {i: puesto for i, linea in enumerate(lineas) if (puesto := leer_puesto(linea, hoy))}
    sin_vineta = {i: puesto for i, puesto in leidos.items() if i not in vinetas}
    return sin_vineta or leidos


def _cargo_y_organizacion(descripcion: str) -> tuple[str, str | None]:
    for separador in _SEPARADORES:
        partes = separador.split(descripcion, maxsplit=1)
        if len(partes) == 2 and partes[0].strip(_BORDES) and partes[1].strip(_BORDES):
            return partes[0].strip(_BORDES), partes[1].strip(_BORDES)
    return descripcion, None


def anios_totales(periodos: Iterable[Periodo]) -> float:
    """Años cubiertos por la unión de los periodos (los solapamientos no se cuentan dos veces)."""
    unidos: list[list[float]] = []
    for periodo in sorted(periodos, key=lambda p: p.desde):
        if unidos and periodo.desde <= unidos[-1][1]:
            unidos[-1][1] = max(unidos[-1][1], periodo.hasta)
        else:
            unidos.append([periodo.desde, periodo.hasta])
    return round(sum(fin - inicio for inicio, fin in unidos), 1)

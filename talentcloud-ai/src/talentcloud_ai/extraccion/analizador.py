"""Análisis completo de un CV: secciones, competencias con años y evidencia, puestos, certificaciones y fragmentos."""

from collections.abc import Sequence
from dataclasses import dataclass
from datetime import date

from talentcloud_ai.contrato.comun import SeccionCv
from talentcloud_ai.contrato.cv import AnalisisCv, Cargo, CompetenciaCatalogo, CompetenciaDetectada, Seccion
from talentcloud_ai.extraccion import certificaciones, fragmentos
from talentcloud_ai.extraccion.competencias import DetectorDeCompetencias, es_mencion_dudosa
from talentcloud_ai.extraccion.experiencia import Periodo, anios_cerca_de, anios_declarados, anios_totales, leer_puesto
from talentcloud_ai.extraccion.secciones import BloqueDeSeccion, segmentar
from talentcloud_ai.extraccion.texto import normalizar_alineado, oraciones, recortar

# Dónde se busca la mejor evidencia de una competencia: la experiencia demuestra más que una lista de habilidades.
_PRIORIDAD_DE_EVIDENCIA = {
    SeccionCv.EXPERIENCIA: 0,
    SeccionCv.PROYECTOS: 1,
    SeccionCv.PERFIL: 2,
    SeccionCv.CERTIFICACIONES: 3,
    SeccionCv.HABILIDADES: 4,
    SeccionCv.EDUCACION: 5,
    SeccionCv.OTROS: 6,
    SeccionCv.ENCABEZADO: 7,
    SeccionCv.IDIOMAS: 8,
    SeccionCv.INTERESES: 9,
}
_SIN_ANALIZAR = frozenset({SeccionCv.DATOS_PERSONALES, SeccionCv.REFERENCIAS})


@dataclass(frozen=True)
class Mencion:
    competencia_id: int
    nombre: str
    oracion: str
    seccion: SeccionCv
    dudosa: bool
    anios_declarados: float | None
    periodo: Periodo | None  # el del puesto en cuya descripción aparece
    orden: int

    @property
    def prioridad(self) -> tuple[bool, int, int]:
        """Orden como evidencia: primero la que declara años (explica mejor el resultado), luego por sección."""
        seccion = _PRIORIDAD_DE_EVIDENCIA.get(self.seccion, len(_PRIORIDAD_DE_EVIDENCIA))
        return self.anios_declarados is None, seccion, self.orden


@dataclass(frozen=True)
class _Recorrido:
    menciones: list[Mencion]
    cargos: list[Cargo]
    periodos: list[Periodo]


class AnalizadorDeCv:
    def __init__(self, catalogo: Sequence[CompetenciaCatalogo], hoy: date) -> None:
        self._detector = DetectorDeCompetencias(catalogo)
        self._hoy = hoy

    def analizar(self, texto: str) -> AnalisisCv:
        bloques = segmentar(texto)
        recorrido = self._recorrer(bloques)

        return AnalisisCv(
            secciones=[Seccion(nombre=b.nombre, titulo=b.titulo, orden=b.orden, texto=b.texto) for b in bloques],
            competencias=self.consolidar(recorrido.menciones),
            experiencia_total_anios=self._experiencia_total(bloques, recorrido.periodos),
            cargos=recorrido.cargos,
            certificaciones=certificaciones.extraer(bloques),
            fragmentos=fragmentos.construir(bloques),
        )

    def menciones(self, texto: str) -> list[Mencion]:
        """Todas las menciones de competencias del catálogo, en orden de aparición."""
        return self.menciones_en(segmentar(texto))

    def menciones_en(self, bloques: list[BloqueDeSeccion]) -> list[Mencion]:
        """Como `menciones`, sobre un CV ya segmentado."""
        return self._recorrer(bloques).menciones

    def _recorrer(self, bloques: list[BloqueDeSeccion]) -> _Recorrido:
        menciones: list[Mencion] = []
        cargos: list[Cargo] = []
        periodos: list[Periodo] = []

        for bloque in bloques:
            if bloque.nombre in _SIN_ANALIZAR:
                continue
            periodo_actual: Periodo | None = None
            for linea in bloque.lineas:
                if bloque.nombre is SeccionCv.EXPERIENCIA and (puesto := leer_puesto(linea, self._hoy)):
                    periodo_actual = puesto.periodo
                    periodos.append(puesto.periodo)
                    cargos.append(
                        Cargo(
                            cargo=puesto.cargo,
                            organizacion=puesto.organizacion,
                            desde=puesto.desde,
                            hasta=puesto.hasta,
                            evidencia=recortar(linea),
                        )
                    )
                for oracion in oraciones(linea):
                    normal = normalizar_alineado(oracion)
                    for encontrada in self._detector.buscar(oracion):
                        menciones.append(
                            Mencion(
                                competencia_id=encontrada.competencia_id,
                                nombre=encontrada.nombre,
                                oracion=oracion,
                                seccion=bloque.nombre,
                                dudosa=es_mencion_dudosa(normal, encontrada.posicion, bloque.nombre),
                                anios_declarados=anios_cerca_de(normal, encontrada.posicion),
                                periodo=periodo_actual,
                                orden=len(menciones),
                            )
                        )
        return _Recorrido(menciones, cargos, periodos)

    @staticmethod
    def consolidar(menciones: Sequence[Mencion]) -> list[CompetenciaDetectada]:
        """Una entrada por competencia: la mejor evidencia y los años que el CV acredita."""
        por_competencia: dict[int, list[Mencion]] = {}
        for mencion in menciones:
            por_competencia.setdefault(mencion.competencia_id, []).append(mencion)

        detectadas: list[CompetenciaDetectada] = []
        for grupo in por_competencia.values():
            firmes = [m for m in grupo if not m.dudosa]
            mejor = min(firmes or grupo, key=lambda m: m.prioridad)
            detectadas.append(
                CompetenciaDetectada(
                    competencia_id=mejor.competencia_id,
                    nombre=mejor.nombre,
                    anios=anios_acreditados(firmes),
                    evidencia=recortar(mejor.oracion),
                    seccion=mejor.seccion,
                    dudosa=not firmes,
                )
            )
        return detectadas

    @staticmethod
    def _experiencia_total(bloques: list[BloqueDeSeccion], periodos: list[Periodo]) -> float | None:
        if periodos:
            return anios_totales(periodos)
        # Sin puestos con fechas, lo que declare el perfil ("8 años de experiencia").
        declarados = [
            d.anios
            for bloque in bloques
            if bloque.nombre in {SeccionCv.PERFIL, SeccionCv.ENCABEZADO}
            for linea in bloque.lineas
            for oracion in oraciones(linea)
            for d in anios_declarados(normalizar_alineado(oracion))
        ]
        return max(declarados, default=None)


def anios_acreditados(menciones: Sequence[Mencion]) -> float | None:
    """Lo mayor entre los años declarados junto a la competencia y los de los puestos donde se usó."""
    candidatos = [m.anios_declarados for m in menciones if m.anios_declarados is not None]
    periodos = {m.periodo for m in menciones if m.periodo is not None}
    if periodos:
        candidatos.append(anios_totales(periodos))
    return round(max(candidatos), 1) if candidatos else None

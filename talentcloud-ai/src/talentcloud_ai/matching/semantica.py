"""Evidencia semántica: el fragmento del CV más parecido a cada requisito (similitud coseno de embeddings).

La similitud se calcula aquí, con el mismo modelo y los mismos fragmentos que Laravel guarda en pgvector:
el servicio no tiene estado y un CV tiene pocas decenas de fragmentos, así que no hace falta consultar la
base de datos ni cambiar el contrato congelado de /matching/evaluar.
"""

from collections.abc import Sequence
from dataclasses import dataclass

from talentcloud_ai.contrato.comun import SeccionCv
from talentcloud_ai.contrato.cv import Fragmento
from talentcloud_ai.contrato.matching import RequisitoInput
from talentcloud_ai.embeddings.base import Embedder
from talentcloud_ai.extraccion.competencias import es_fragmento_dudoso


@dataclass(frozen=True)
class EvidenciaSemantica:
    texto: str
    seccion: SeccionCv
    similitud: float  # coseno en [-1, 1]; los vectores tienen norma 1
    # El fragmento es un interés o un nivel básico ("Kubernetes (interés en aprender)…"): no acredita experiencia.
    dudosa: bool = False


def texto_de_consulta(requisito: RequisitoInput) -> str:
    """Texto con el que se busca el requisito: «Kubernetes (k8s). Operación de clústeres en producción.»

    Sin los años mínimos: "al menos 3 años" acercaría el requisito a cualquier frase con años de
    experiencia, y los años ya los evalúa la coincidencia exacta. Laravel genera el embedding de los
    requisitos con el mismo texto (IndexarRequisitos::textoParaEmbedding).
    """
    texto = requisito.competencia
    if requisito.sinonimos:
        texto += " (" + ", ".join(requisito.sinonimos) + ")"
    if requisito.descripcion and requisito.descripcion.strip():
        texto += ". " + requisito.descripcion.strip().rstrip(".")
    return texto + "."


def vistas_de_consulta(requisito: RequisitoInput) -> list[str]:
    """El requisito completo, su nombre (con sinónimos) y su descripción, por separado.

    Un nombre escueto ("Mensajería asíncrona") y una descripción ("Integración mediante colas de mensajes")
    se parecen a frases distintas del CV; se toma la mejor de las vistas. Medido en evaluacion/: mejora la
    exactitud sin añadir falsos "Coincide".
    """
    nombre = requisito.competencia + (f" ({', '.join(requisito.sinonimos)})" if requisito.sinonimos else "")
    descripcion = requisito.descripcion.strip() if requisito.descripcion else ""
    return list(dict.fromkeys(v for v in (texto_de_consulta(requisito), nombre, descripcion) if v))


def _coseno(a: Sequence[float], b: Sequence[float]) -> float:
    return sum(x * y for x, y in zip(a, b, strict=True))


class BuscadorSemantico:
    """Codifica los fragmentos de un CV una vez y busca, para cada requisito, el más parecido."""

    def __init__(self, embedder: Embedder, fragmentos: Sequence[Fragmento]) -> None:
        self._embedder = embedder
        self._fragmentos = list(fragmentos)
        self._vectores = embedder.codificar([f.texto for f in self._fragmentos])

    def mas_parecidos(self, requisitos: Sequence[RequisitoInput]) -> dict[int, EvidenciaSemantica]:
        """requisito_id → evidencia más parecida (vacío si el CV no tiene fragmentos)."""
        if not self._fragmentos or not requisitos:
            return {}

        vistas = [vistas_de_consulta(r) for r in requisitos]
        # Todas las vistas de todos los requisitos en un solo lote.
        vectores = iter(self._embedder.codificar([v for grupo in vistas for v in grupo]))

        resultado: dict[int, EvidenciaSemantica] = {}
        for requisito, grupo in zip(requisitos, vistas, strict=True):
            consultas = [next(vectores) for _ in grupo]
            similitud, fragmento = max(
                (
                    (_coseno(consulta, vector), fragmento)
                    for consulta in consultas
                    for vector, fragmento in zip(self._vectores, self._fragmentos, strict=True)
                ),
                key=lambda par: par[0],
            )
            resultado[requisito.id] = EvidenciaSemantica(
                fragmento.texto,
                fragmento.seccion,
                round(similitud, 4),
                es_fragmento_dudoso(fragmento.texto, fragmento.seccion),
            )
        return resultado

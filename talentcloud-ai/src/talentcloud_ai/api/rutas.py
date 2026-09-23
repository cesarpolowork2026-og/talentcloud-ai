"""Endpoints del servicio. La lógica vive en extraccion/, embeddings/ y matching/; aquí solo se orquesta."""

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from talentcloud_ai import __version__, casos_de_uso
from talentcloud_ai.api.dependencias import EmbedderDep, HoyDep, verificar_api_key
from talentcloud_ai.contrato.cv import AnalisisCv, AnalisisCvInput
from talentcloud_ai.contrato.embeddings import EmbeddingsInput, EmbeddingsResultado
from talentcloud_ai.contrato.matching import MatchInput, MatchResult
from talentcloud_ai.matching.evaluador import EvaluadorDeMatching


class Salud(BaseModel):
    estado: str
    version: str
    modelo_embeddings: str
    dimensiones_embeddings: int


publico = APIRouter()
protegido = APIRouter(dependencies=[Depends(verificar_api_key)])


@publico.get("/health", summary="Estado del servicio")
def salud(embedder: EmbedderDep) -> Salud:
    return Salud(
        estado="ok",
        version=__version__,
        modelo_embeddings=embedder.modelo,
        dimensiones_embeddings=embedder.dimensiones,
    )


@protegido.post("/cv/analizar", summary="Extrae secciones, competencias, experiencia y certificaciones de un CV")
def analizar_cv(entrada: AnalisisCvInput, embedder: EmbedderDep, hoy: HoyDep) -> AnalisisCv:
    return casos_de_uso.analizar_cv(entrada, embedder, hoy)


@protegido.post("/requisitos/embeddings", summary="Genera los embeddings de los requisitos de una vacante")
def embeddings_de_requisitos(entrada: EmbeddingsInput, embedder: EmbedderDep) -> EmbeddingsResultado:
    return casos_de_uso.generar_embeddings(entrada, embedder)


@protegido.post("/matching/evaluar", summary="Evalúa una postulación requisito por requisito, con evidencia")
def evaluar_matching(entrada: MatchInput, embedder: EmbedderDep, hoy: HoyDep) -> MatchResult:
    return EvaluadorDeMatching(hoy, embedder).evaluar(entrada)

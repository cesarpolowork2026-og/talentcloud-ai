"""Endpoints del servicio. La lógica vive en extraccion/, embeddings/ y matching/; aquí solo se orquesta."""

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from talentcloud_ai import __version__
from talentcloud_ai.api.dependencias import EmbedderDep, HoyDep, verificar_api_key
from talentcloud_ai.contrato.cv import AnalisisCv, AnalisisCvInput
from talentcloud_ai.contrato.embeddings import Embedding, EmbeddingsInput, EmbeddingsResultado
from talentcloud_ai.contrato.matching import MatchInput, MatchResult
from talentcloud_ai.extraccion.analizador import AnalizadorDeCv
from talentcloud_ai.matching.evaluador import EvaluadorDeMatching


class Salud(BaseModel):
    estado: str
    version: str
    modelo_embeddings: str


publico = APIRouter()
protegido = APIRouter(dependencies=[Depends(verificar_api_key)])


@publico.get("/health", summary="Estado del servicio")
def salud(embedder: EmbedderDep) -> Salud:
    return Salud(estado="ok", version=__version__, modelo_embeddings=embedder.modelo)


@protegido.post("/cv/analizar", summary="Extrae secciones, competencias, experiencia y certificaciones de un CV")
def analizar_cv(entrada: AnalisisCvInput, hoy: HoyDep) -> AnalisisCv:
    return AnalizadorDeCv(entrada.catalogo, hoy).analizar(entrada.cv.texto)


@protegido.post("/requisitos/embeddings", summary="Genera los embeddings de los requisitos de una vacante")
def embeddings_de_requisitos(entrada: EmbeddingsInput, embedder: EmbedderDep) -> EmbeddingsResultado:
    vectores = embedder.codificar([t.texto for t in entrada.textos])
    return EmbeddingsResultado(
        modelo=embedder.modelo,
        dimensiones=embedder.dimensiones,
        embeddings=[Embedding(id=t.id, vector=v) for t, v in zip(entrada.textos, vectores, strict=True)],
    )


@protegido.post("/matching/evaluar", summary="Evalúa una postulación requisito por requisito, con evidencia")
def evaluar_matching(entrada: MatchInput, hoy: HoyDep) -> MatchResult:
    return EvaluadorDeMatching(hoy).evaluar(entrada)

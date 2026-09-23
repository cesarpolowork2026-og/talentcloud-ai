"""Contrato del matching (versión 1, congelado el día 11).

Réplica exacta de App\\Matching\\MatchInput::aArreglo() y MatchResult::aArreglo() de Laravel.
Los ejemplos de contrato/ejemplos/ los validan ambos lados.
"""

from typing import Annotated, Literal

from pydantic import Field

from talentcloud_ai.contrato.comun import (
    MAX_NOMBRE,
    CvInput,
    Entrada,
    Id,
    Motor,
    NivelCoincidencia,
    Nombre,
    Salida,
    TipoRequisito,
)

VERSION: Literal[1] = 1


class VacanteInput(Entrada):
    titulo: Nombre
    descripcion: str | None = None


class RequisitoInput(Entrada):
    id: Id
    competencia: Nombre
    sinonimos: Annotated[list[Annotated[str, Field(max_length=MAX_NOMBRE)]], Field(max_length=50, default_factory=list)]
    tipo: TipoRequisito
    peso: Annotated[int, Field(ge=1, le=5)]
    anios_minimos: Annotated[int, Field(ge=0, le=60)] | None = None
    descripcion: str | None = None


class MatchInput(Entrada):
    version: Literal[1]
    postulacion_id: Id
    vacante: VacanteInput
    requisitos: Annotated[list[RequisitoInput], Field(max_length=100)]
    cv: CvInput


class RequisitoMatch(Salida):
    requisito_id: int
    competencia: str
    porcentaje: Annotated[int, Field(ge=0, le=100)]
    nivel: NivelCoincidencia
    evidencia: str | None
    motivo: str


class MatchResult(Salida):
    version: Literal[1] = VERSION
    score: Annotated[float, Field(ge=0, le=100)]
    motor: Motor = Motor.HTTP
    resumen: str | None
    requisitos: list[RequisitoMatch]

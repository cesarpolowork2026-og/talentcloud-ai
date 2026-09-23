"""Tipos compartidos por los contratos del servicio."""

from enum import StrEnum
from typing import Annotated

from pydantic import BaseModel, ConfigDict, Field

# Límites de tamaño: protegen al servicio de cuerpos desproporcionados.
MAX_TEXTO_CV = 200_000
MAX_NOMBRE = 200

Nombre = Annotated[str, Field(min_length=1, max_length=MAX_NOMBRE)]
Id = Annotated[int, Field(ge=1)]


class Entrada(BaseModel):
    """Cuerpo recibido: lector tolerante (ignora campos que aún no conoce); la versión la fija cada contrato."""

    model_config = ConfigDict(extra="ignore", frozen=True)


class Salida(BaseModel):
    """Cuerpo devuelto: estricto, para no enviar nada fuera del contrato."""

    model_config = ConfigDict(extra="forbid", frozen=True)


class TipoRequisito(StrEnum):
    OBLIGATORIO = "obligatorio"
    DESEABLE = "deseable"


class NivelCoincidencia(StrEnum):
    COINCIDE = "coincide"
    REVISAR = "revisar"
    NO_ENCONTRADO = "no_encontrado"


class Motor(StrEnum):
    HTTP = "http"


class SeccionCv(StrEnum):
    """Secciones reconocidas de un CV. Caben en cv_fragmentos.seccion (40 caracteres)."""

    ENCABEZADO = "encabezado"
    PERFIL = "perfil"
    EXPERIENCIA = "experiencia"
    EDUCACION = "educacion"
    HABILIDADES = "habilidades"
    CERTIFICACIONES = "certificaciones"
    IDIOMAS = "idiomas"
    PROYECTOS = "proyectos"
    INTERESES = "intereses"
    DATOS_PERSONALES = "datos_personales"
    REFERENCIAS = "referencias"
    OTROS = "otros"


class CvInput(Entrada):
    id: Id
    texto: Annotated[str, Field(max_length=MAX_TEXTO_CV)]

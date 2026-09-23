"""Dependencias inyectables de FastAPI (se sustituyen en los tests con dependency_overrides)."""

import secrets
from datetime import date, datetime
from typing import Annotated
from zoneinfo import ZoneInfo

from fastapi import Depends, HTTPException, Request, Security, status
from fastapi.security import APIKeyHeader

from talentcloud_ai.config import Settings
from talentcloud_ai.embeddings.base import Embedder

_ZONA = ZoneInfo("America/Lima")
_cabecera_api_key = APIKeyHeader(name="X-Api-Key", auto_error=False)


def obtener_settings_de_app(request: Request) -> Settings:
    settings: Settings = request.app.state.settings
    return settings


def obtener_embedder(request: Request) -> Embedder:
    embedder: Embedder = request.app.state.embedder
    return embedder


def hoy() -> date:
    """Fecha de referencia para "actualidad" en los periodos de experiencia."""
    return datetime.now(_ZONA).date()


def verificar_api_key(
    settings: Annotated[Settings, Depends(obtener_settings_de_app)],
    api_key: Annotated[str | None, Security(_cabecera_api_key)],
) -> None:
    """Exige la cabecera X-Api-Key cuando el servicio tiene una clave configurada (comparación en tiempo constante)."""
    esperada = settings.api_key.get_secret_value() if settings.api_key else None
    if esperada is None:
        return
    if api_key is None or not secrets.compare_digest(api_key.encode(), esperada.encode()):
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, detail="Clave de API no válida.")


SettingsDep = Annotated[Settings, Depends(obtener_settings_de_app)]
EmbedderDep = Annotated[Embedder, Depends(obtener_embedder)]
HoyDep = Annotated[date, Depends(hoy)]

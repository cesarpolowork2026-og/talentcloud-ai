"""Aplicación FastAPI. `uvicorn talentcloud_ai.main:app` o, en tests, `crear_app(settings)`."""

import logging
import time
from collections.abc import AsyncIterator, Awaitable, Callable
from contextlib import asynccontextmanager

from fastapi import FastAPI, Request, Response
from fastapi.responses import JSONResponse

from talentcloud_ai import __version__
from talentcloud_ai.api.rutas import protegido, publico
from talentcloud_ai.config import Settings, obtener_settings
from talentcloud_ai.embeddings.fabrica import crear_embedder

logger = logging.getLogger("talentcloud_ai")


def crear_app(settings: Settings | None = None) -> FastAPI:
    settings = settings or obtener_settings()
    logging.basicConfig(level=settings.log_level, format="%(asctime)s %(levelname)s %(name)s %(message)s")

    @asynccontextmanager
    async def ciclo_de_vida(app: FastAPI) -> AsyncIterator[None]:
        # El embedder (y su modelo) se carga una sola vez, al iniciar.
        app.state.settings = settings
        app.state.embedder = crear_embedder(settings)
        logger.info("Servicio listo (entorno=%s, embeddings=%s)", settings.entorno, app.state.embedder.modelo)
        yield

    app = FastAPI(
        title="TalentCloud AI",
        version=__version__,
        lifespan=ciclo_de_vida,
        # La documentación interactiva solo fuera de producción.
        docs_url=None if settings.es_produccion else "/docs",
        redoc_url=None,
        openapi_url=None if settings.es_produccion else "/openapi.json",
    )

    @app.middleware("http")
    async def registrar_peticion(request: Request, siguiente: Callable[[Request], Awaitable[Response]]) -> Response:
        # Solo metadatos: nunca se registra el cuerpo (contiene el texto del CV).
        inicio = time.perf_counter()
        respuesta = await siguiente(request)
        duracion = (time.perf_counter() - inicio) * 1000
        logger.info("%s %s -> %s (%.0f ms)", request.method, request.url.path, respuesta.status_code, duracion)
        return respuesta

    @app.exception_handler(Exception)
    async def error_inesperado(request: Request, error: Exception) -> JSONResponse:
        logger.exception("Error no controlado en %s %s", request.method, request.url.path)
        return JSONResponse(status_code=500, content={"detail": "Error interno del servicio de IA."})

    app.include_router(publico)
    app.include_router(protegido)
    return app


app = crear_app()

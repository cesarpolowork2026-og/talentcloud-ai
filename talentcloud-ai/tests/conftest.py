import json
from collections.abc import Callable, Iterator
from datetime import date
from pathlib import Path

import pytest
from fastapi.testclient import TestClient
from pydantic import SecretStr

from talentcloud_ai.api.dependencias import hoy
from talentcloud_ai.config import Settings
from talentcloud_ai.contrato.cv import CompetenciaCatalogo
from talentcloud_ai.main import crear_app

DATOS = Path(__file__).parent / "datos"
EJEMPLOS = Path(__file__).parents[1] / "contrato" / "ejemplos"
HOY = date(2026, 9, 23)
API_KEY = "clave-de-prueba"


@pytest.fixture(scope="session")
def catalogo() -> list[CompetenciaCatalogo]:
    """Copia del catálogo base de Laravel (CatalogoCompetenciasSeeder)."""
    datos = json.loads((DATOS / "catalogo.json").read_text(encoding="utf-8"))
    return [CompetenciaCatalogo.model_validate(c) for c in datos]


@pytest.fixture(scope="session")
def cv() -> Callable[[str], str]:
    """Texto de un CV de demostración (los mismos que siembra DemoSeeder)."""

    def leer(nombre: str) -> str:
        return (DATOS / "cvs" / f"{nombre}.txt").read_text(encoding="utf-8")

    return leer


@pytest.fixture(scope="session")
def ejemplo() -> Callable[[str], dict[str, object]]:
    """Ejemplos del contrato, compartidos con los tests de Laravel."""

    def leer(nombre: str) -> dict[str, object]:
        datos: dict[str, object] = json.loads((EJEMPLOS / f"{nombre}.json").read_text(encoding="utf-8"))
        return datos

    return leer


@pytest.fixture
def cliente() -> Iterator[TestClient]:
    app = crear_app(Settings(entorno="testing", api_key=SecretStr(API_KEY)))
    app.dependency_overrides[hoy] = lambda: HOY
    with TestClient(app, headers={"X-Api-Key": API_KEY}) as cliente:
        yield cliente

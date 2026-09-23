"""Configuración del servicio, leída de variables de entorno con el prefijo TALENTCLOUD_AI_."""

from functools import lru_cache
from typing import Literal

from pydantic import SecretStr
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_prefix="TALENTCLOUD_AI_", env_file=".env", extra="ignore")

    entorno: Literal["local", "testing", "production"] = "local"

    # Clave compartida con Laravel (cabecera X-Api-Key). Sin clave, el servicio no la exige:
    # en GCP el acceso lo restringe además Cloud Run (solo invocación autenticada).
    api_key: SecretStr | None = None

    # "sentence-transformers" requiere el extra "ml". "determinista" no usa modelo: para tests y para
    # desarrollar sin descargar los pesos (los vectores no captan significado).
    embeddings_backend: Literal["determinista", "sentence-transformers"] = "sentence-transformers"
    modelo_embeddings: str = "paraphrase-multilingual-MiniLM-L12-v2"
    dimensiones_embeddings: int = 384

    log_level: str = "INFO"

    @property
    def es_produccion(self) -> bool:
        return self.entorno == "production"


@lru_cache
def obtener_settings() -> Settings:
    return Settings()

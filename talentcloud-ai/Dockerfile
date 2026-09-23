# syntax=docker/dockerfile:1.7
# Servicio de IA de TalentCloud (Cloud Run). El modelo va dentro de la imagen: arranca sin descargar
# nada y funciona sin salida a internet.
#
#   docker build -t talentcloud-ai .
#   docker run -p 8080:8080 -e TALENTCLOUD_AI_API_KEY=… talentcloud-ai

ARG PYTHON_VERSION=3.11

# — Construcción: entorno virtual con las dependencias bloqueadas y el modelo descargado —
FROM python:${PYTHON_VERSION}-slim AS construccion

COPY --from=ghcr.io/astral-sh/uv:0.12 /uv /bin/uv
ENV UV_COMPILE_BYTECODE=1 \
    UV_LINK_MODE=copy \
    UV_PYTHON_DOWNLOADS=never \
    HF_HOME=/modelos

WORKDIR /app

# Primero solo las dependencias: esta capa se reutiliza mientras no cambien pyproject.toml ni uv.lock.
COPY pyproject.toml uv.lock README.md ./
RUN --mount=type=cache,target=/root/.cache/uv \
    uv sync --frozen --no-dev --extra ml --no-install-project

COPY src ./src
RUN --mount=type=cache,target=/root/.cache/uv \
    uv sync --frozen --no-dev --extra ml --no-editable

ARG MODELO=paraphrase-multilingual-MiniLM-L12-v2
RUN /app/.venv/bin/python -c "import sys; from sentence_transformers import SentenceTransformer; SentenceTransformer(sys.argv[1])" "${MODELO}"

# — Ejecución: solo el entorno y el modelo, con un usuario sin privilegios —
FROM python:${PYTHON_VERSION}-slim AS ejecucion

ARG MODELO=paraphrase-multilingual-MiniLM-L12-v2
RUN useradd --system --uid 10001 --no-create-home talentcloud

WORKDIR /app
COPY --from=construccion /app/.venv /app/.venv
COPY --from=construccion /modelos /modelos

ENV PATH=/app/.venv/bin:$PATH \
    PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    HF_HOME=/modelos \
    HF_HUB_OFFLINE=1 \
    TRANSFORMERS_OFFLINE=1 \
    TALENTCLOUD_AI_ENTORNO=production \
    TALENTCLOUD_AI_EMBEDDINGS_BACKEND=sentence-transformers \
    TALENTCLOUD_AI_MODELO_EMBEDDINGS=${MODELO} \
    PORT=8080

USER talentcloud
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD python -c "import os, urllib.request; urllib.request.urlopen(f'http://127.0.0.1:{os.environ[\"PORT\"]}/health', timeout=4)"

# Un proceso por instancia (el modelo vive en memoria; Cloud Run escala con instancias). Cloud Run fija PORT.
# El registro de peticiones lo hace la aplicación (sin cuerpos), así que se omite el de uvicorn.
CMD ["sh", "-c", "exec uvicorn talentcloud_ai.main:app --host 0.0.0.0 --port ${PORT} --workers 1 --no-access-log"]

# TalentCloud AI — servicio de IA

Servicio FastAPI que extrae información de los CV y evalúa cada postulación requisito por requisito, con
evidencia textual y un motivo en lenguaje llano. **Sugiere y explica; la decisión es del reclutador.**

Es *stateless*: Laravel le envía el texto (ya extraído del PDF/DOCX), el catálogo de competencias y los
requisitos, y guarda lo que el servicio devuelve. El servicio no tiene base de datos ni credenciales.

## Uso

Requiere Python 3.11 y [uv](https://docs.astral.sh/uv/).

```bash
uv sync                                                              # entorno .venv según uv.lock
uv run uvicorn talentcloud_ai.main:app --app-dir src --port 8001     # http://127.0.0.1:8001/docs
uv run pytest                                                        # tests (añadir --cov para cobertura)
uv run ruff check . && uv run ruff format --check . && uv run mypy   # estilo y tipos (lo mismo que el CI)
```

Configuración por variables de entorno con el prefijo `TALENTCLOUD_AI_` (ver `.env.example`). Si se define
`TALENTCLOUD_AI_API_KEY`, los endpoints protegidos exigen la cabecera `X-Api-Key`.

> **Windows:** Python 3.11 lee los `.pth` con la página de códigos ANSI y la ruta del proyecto tiene tildes
> ("Décimo"), así que la instalación editable no se importa. Por eso `--app-dir src` en uvicorn y
> `pythonpath = ["src"]` en pytest; para scripts sueltos, `PYTHONPATH=src`. En Docker y en el CI no ocurre.

## Endpoints

| Método | Ruta | Qué hace |
| --- | --- | --- |
| GET | `/health` | Estado y modelo de embeddings cargado (sin clave). |
| POST | `/cv/analizar` | Secciones, competencias del catálogo (con años, evidencia y si la mención es dudosa), puestos, experiencia total, certificaciones y fragmentos para embeddings. |
| POST | `/requisitos/embeddings` | Vectores normalizados de los textos de los requisitos. |
| POST | `/matching/evaluar` | `MatchInput` → `MatchResult`: porcentaje, nivel, evidencia y motivo por requisito; puntaje ponderado y resumen. |

## Contrato con Laravel

Versión 1, congelada. Los modelos de `src/talentcloud_ai/contrato/` replican `App\Matching\*` de Laravel.
`contrato/ejemplos/*.json` son la referencia compartida: los validan `tests/test_contrato.py` y
`talentcloud/tests/Unit/Matching/ContratoTest.php`. Cambiar el contrato exige subir la versión en ambos lados.

## Estructura

```
src/talentcloud_ai/
  api/             rutas FastAPI y dependencias (clave de API, fecha de referencia, embedder)
  contrato/        modelos Pydantic de entrada y salida (versionados)
  extraccion/      texto, secciones, competencias, experiencia, certificaciones, fragmentos, analizador
  embeddings/      interfaz Embedder; "determinista" (sin modelo) hasta el día 13
  matching/        calificación por requisito, umbrales y puntaje global
  explicabilidad/  motivos y resumen en lenguaje llano
tests/datos/       CV de demostración y copia del catálogo de competencias de Laravel
```

## Privacidad

- Nunca se registra el cuerpo de las peticiones (contiene el CV); solo método, ruta, estado y duración.
- Los fragmentos para embeddings excluyen el encabezado (nombre, contacto), los datos personales y las
  referencias: el matching no usa nombre, género, edad, foto ni lugar de nacimiento.
- La documentación interactiva (`/docs`) se desactiva con `TALENTCLOUD_AI_ENTORNO=production`.

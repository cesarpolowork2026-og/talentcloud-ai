# TalentCloud AI — servicio de IA

Servicio FastAPI que extrae información de los CV y evalúa cada postulación requisito por requisito, con
evidencia textual y un motivo en lenguaje llano. **Sugiere y explica; la decisión es del reclutador.**

Es *stateless*: Laravel le envía el texto (ya extraído del PDF/DOCX), el catálogo de competencias y los
requisitos, y guarda lo que el servicio devuelve. El servicio no tiene base de datos ni credenciales.

## Uso

Requiere Python 3.11 y [uv](https://docs.astral.sh/uv/).

```bash
uv sync --extra ml                                                   # entorno .venv según uv.lock, con el modelo
uv run --extra ml uvicorn talentcloud_ai.main:app --app-dir src --port 8001   # http://127.0.0.1:8001/docs
uv run pytest                                                        # tests (añadir --cov para cobertura)
uv run pytest -m modelo                                              # solo el test con el modelo real
uv run ruff check . && uv run ruff format --check . && uv run mypy   # estilo y tipos (lo mismo que el CI)
```

Configuración por variables de entorno con el prefijo `TALENTCLOUD_AI_` (ver `.env.example`). Si se define
`TALENTCLOUD_AI_API_KEY`, los endpoints protegidos exigen la cabecera `X-Api-Key`.

**Embeddings.** `paraphrase-multilingual-MiniLM-L12-v2` (español e inglés, 384 dimensiones, vectores de norma 1).
Va en el extra `ml` con PyTorch **solo para CPU** (índice `pytorch-cpu`), para que el CI y los tests no lo
descarguen y la imagen de Cloud Run sea pequeña. Se carga una vez al iniciar; si el modelo no produce 384
dimensiones (la columna `vector(384)` de Laravel) el servicio no arranca, y sin el extra tampoco (indica cómo
instalarlo). `TALENTCLOUD_AI_EMBEDDINGS_BACKEND=determinista` usa un embedder por hashing sin modelo (tests).
La primera ejecución descarga el modelo en la caché de Hugging Face (unos 470 MB).

> **Windows:** Python 3.11 lee los `.pth` con la página de códigos ANSI y la ruta del proyecto tiene tildes
> ("Décimo"), así que la instalación editable no se importa. Por eso `--app-dir src` en uvicorn y
> `pythonpath = ["src"]` en pytest; para scripts sueltos, `PYTHONPATH=src`. En Docker y en el CI no ocurre.

## Imagen Docker (Cloud Run)

```bash
docker build -t talentcloud-ai .                                   # incluye el modelo (sin descargas al arrancar)
docker run -p 8080:8080 -e TALENTCLOUD_AI_API_KEY=… talentcloud-ai
```

Multietapa con `uv sync --frozen` (dependencias en su propia capa), PyTorch CPU, modelo en `/modelos` con
`HF_HUB_OFFLINE=1`, usuario sin privilegios, `PORT` de Cloud Run (8080 por defecto), un proceso por instancia y
`/docs` desactivado (`TALENTCLOUD_AI_ENTORNO=production`). Unos 870 MB comprimida; arranca en ~13 s.

## Endpoints

| Método | Ruta | Qué hace |
| --- | --- | --- |
| GET | `/health` | Estado y modelo de embeddings cargado (sin clave). |
| POST | `/cv/analizar` | Secciones, competencias del catálogo (con años, evidencia y si la mención es dudosa), puestos, experiencia total, certificaciones y fragmentos con su embedding. |
| POST | `/requisitos/embeddings` | Vectores normalizados de los textos de los requisitos. |
| POST | `/matching/evaluar` | `MatchInput` → `MatchResult`: porcentaje, nivel, evidencia y motivo por requisito; puntaje ponderado y resumen. |

## Matching

Por requisito, `matching/calificacion.py` decide con este orden de confianza:

1. **Coincidencia exacta** (competencia o sinónimo en el CV): años acreditados frente a los pedidos, evidencia
   laboral frente a una lista de habilidades, y menciones dudosas ("interés en aprender", "nociones") a "Revisar".
2. **Similitud semántica** si el CV no nombra la competencia: el fragmento más parecido de los que describen lo que
   la persona hizo (sin puestos ni formación), comparando el requisito completo, su nombre y su descripción. Nunca
   supera a una coincidencia exacta, y un fragmento dudoso nunca da "Coincide".
3. **Sin evidencia**: 0 %; si es obligatorio, se dice en el motivo y en el resumen.

Los umbrales de similitud (`matching/umbrales.py`) se calibran con el conjunto de `evaluacion/`, y
`evaluacion/evaluar.py` compara el servicio con el análisis básico de Laravel (resumen en `evaluacion/RESULTADOS.md`):

```bash
uv run --extra ml python evaluacion/calibrar.py    # (Windows: PYTHONPATH=src) → evaluacion/calibracion.json
php artisan talentcloud:evaluar-motor-basico       # desde talentcloud/ → evaluacion/predicciones_motor_basico.json
uv run --extra ml python evaluacion/evaluar.py     # → evaluacion/resultados.json y RESULTADOS.md
```

`evaluacion/conjunto.json` tiene 145 casos (29 requisitos × 5 CV de demostración) etiquetados por el equipo,
pendientes de revisión por un reclutador. El informe guarda umbrales, exactitud (híbrido, solo exacta, validación
cruzada dejando un CV fuera), matriz de confusión y cada error con su similitud y fragmento. Los umbrales se copian
a mano a `umbrales.py` (un test exige que coincidan con el informe).

## Contrato con Laravel

Versión 1, congelada. Los modelos de `src/talentcloud_ai/contrato/` replican `App\Matching\*` de Laravel.
`contrato/ejemplos/*.json` son la referencia compartida: los validan `tests/test_contrato.py`,
`talentcloud/tests/Unit/Matching/ContratoTest.php` (matching) y `talentcloud/tests/Feature/Ia/ServicioIaTest.php`
(análisis de CV). Cambiar el contrato exige subir la versión en ambos lados.

## Estructura

```
src/talentcloud_ai/
  api/             rutas FastAPI y dependencias (clave de API, fecha de referencia, embedder)
  contrato/        modelos Pydantic de entrada y salida (versionados)
  extraccion/      texto, secciones, competencias, experiencia, certificaciones, fragmentos, analizador
  embeddings/      interfaz Embedder: sentence-transformers (extra ml) y "determinista" (sin modelo, tests)
  casos_de_uso.py  análisis de CV con embeddings y embeddings de textos (lo que llaman las rutas)
  matching/        evaluador, calificación (exacta + semántica), búsqueda semántica y umbrales calibrados
  calibracion.py   búsqueda de umbrales, validación cruzada y matriz de confusión (la usa evaluacion/calibrar.py)
  explicabilidad/  motivos y resumen en lenguaje llano
tests/datos/       CV de demostración y copia del catálogo de competencias de Laravel
```

## Privacidad

- Nunca se registra el cuerpo de las peticiones (contiene el CV); solo método, ruta, estado y duración.
- Los fragmentos para embeddings excluyen el encabezado (nombre, contacto), los datos personales y las
  referencias, y cualquier línea con correo, teléfono, URL de perfil o rasgos personales (`extraccion/privacidad.py`):
  el matching no usa nombre, género, edad, foto ni lugar de nacimiento.
- La documentación interactiva (`/docs`) se desactiva con `TALENTCLOUD_AI_ENTORNO=production`.
- En producción el servicio no arranca sin `TALENTCLOUD_AI_API_KEY`: sin clave aceptaría peticiones de
  cualquiera que lo alcance (fuera de producción, sin clave no la exige).

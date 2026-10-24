# API REST de TalentCloud AI (v1)

API pública y **de solo lectura** con las vacantes abiertas, es decir, lo mismo que muestra la bolsa de empleos (`/empleos`). Sirve para que otras aplicaciones (un portal de empleo, una app móvil, un agregador) consulten las ofertas sin pasar por las pantallas.

- **URL base:** `https://talentcloudia.online/api/v1` (en local, `http://localhost:8000/api/v1`).
- **Formato:** JSON en UTF-8. Los errores también responden JSON.
- **Autenticación:** ninguna. Solo se expone información que ya es pública; no hay endpoints de escritura ni de datos de candidatos o de empresas.
- **Límite:** 60 peticiones por minuto y por IP. Al excederlo responde `429`.
- **Qué nunca devuelve:** borradores, vacantes cerradas, vacantes de empresas suspendidas, postulaciones, candidatos, CV ni datos internos.

## Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/salud` | Comprueba que la API y la base de datos responden. |
| GET | `/vacantes` | Lista paginada de vacantes abiertas, con filtros. |
| GET | `/vacantes/{id}` | Detalle de una vacante abierta (incluye la descripción). |

Cualquier otro método sobre estas rutas (`POST`, `PUT`, `DELETE`) responde `405`.

### GET /salud

```bash
curl https://talentcloudia.online/api/v1/salud
```

```json
{ "estado": "ok", "base_de_datos": true, "version": "v1" }
```

Si no llega a la base de datos responde `503` con `{"estado": "degradado", "base_de_datos": false}`.

### GET /vacantes

Parámetros de consulta (todos opcionales):

| Parámetro | Tipo | Descripción |
|---|---|---|
| `q` | texto, máx. 100 | Busca en título, ubicación y nombre de la empresa. No distingue mayúsculas ni tildes. |
| `modalidad` | `remoto`, `presencial` o `hibrido` | Filtra por modalidad. |
| `por_pagina` | entero 1–50 (12 por defecto) | Tamaño de página. |
| `page` | entero | Número de página. |

Ordenadas de la más reciente a la más antigua.

```bash
curl "https://talentcloudia.online/api/v1/vacantes?modalidad=remoto&q=desarrollador&por_pagina=2"
```

```json
{
  "data": [
    {
      "id": 41,
      "titulo": "Desarrollador Backend",
      "modalidad": "remoto",
      "ubicacion": null,
      "jornada": "completa",
      "sueldo": {
        "minimo": 4500,
        "maximo": 6000,
        "moneda": "PEN",
        "periodo": "mes",
        "texto": "S/ 4 500 – 6 000 al mes"
      },
      "publicada_en": "2026-10-18T09:30:00-05:00",
      "empresa": { "nombre": "Nimbus Corp", "sector": "Tecnología", "sitio_web": "https://nimbus.example" },
      "requisitos": [
        { "competencia": "PHP", "tipo": "obligatorio", "anios_minimos": 3 },
        { "competencia": "Docker", "tipo": "deseable", "anios_minimos": null }
      ],
      "url": "https://talentcloudia.online/empleos/41"
    }
  ],
  "links": { "first": "…?page=1", "last": "…?page=3", "prev": null, "next": "…?page=2" },
  "meta": { "current_page": 1, "per_page": 2, "total": 5, "last_page": 3 }
}
```

(Los valores del ejemplo son ilustrativos; la estructura es la real.)

### GET /vacantes/{id}

Devuelve `{ "data": { … } }` con los mismos campos que el listado más `descripcion`.

```bash
curl https://talentcloudia.online/api/v1/vacantes/41
```

Si la vacante no existe **o no es pública** (borrador, cerrada, empresa suspendida) responde `404` con `{"mensaje": "Vacante no encontrada."}`; no se distingue un caso del otro.

## Campos de una vacante

| Campo | Tipo | Notas |
|---|---|---|
| `id` | entero | |
| `titulo` | texto | |
| `modalidad` | texto | `remoto`, `presencial`, `hibrido`. |
| `ubicacion` | texto o `null` | En remoto es una cobertura opcional; en presencial/híbrido siempre viene. |
| `jornada` | texto o `null` | `completa` o `parcial`; `null` = no indicada. |
| `sueldo.minimo` / `sueldo.maximo` | entero o `null` | Soles (`PEN`) al mes, sin descuentos. Ambos `null` = a convenir. |
| `sueldo.texto` | texto o `null` | Lectura humana del rango. |
| `publicada_en` | fecha ISO 8601 | |
| `empresa` | objeto | `nombre`, `sector`, `sitio_web`. |
| `requisitos` | lista | `competencia`, `tipo` (`obligatorio`/`deseable`), `anios_minimos` (o `null`). |
| `descripcion` | texto | Solo en el detalle. |
| `url` | texto | Página pública de la vacante, desde donde se postula. |

## Errores

| Código | Cuándo | Cuerpo |
|---|---|---|
| 404 | Vacante no pública o ruta inexistente | `{"mensaje": "…"}` |
| 405 | Método no permitido (la API es de solo lectura) | `{"message": "…"}` |
| 422 | Parámetro inválido, p. ej. `modalidad=volando` | `{"message": "…", "errors": {"modalidad": ["…"]}}` |
| 429 | Más de 60 peticiones por minuto | `{"message": "Too Many Requests."}` con la cabecera `Retry-After` |
| 503 | `/salud` sin acceso a la base de datos | ver arriba |

## Decisiones de diseño

- **Versionada en la ruta (`/v1`)**: un cambio que rompa el contrato crea `/v2` sin afectar a quien use `/v1`.
- **Misma lectura que la web**: usa `VacanteRepository::publicadas()`, así que cualquier regla de qué es público (por ejemplo, que la empresa esté activa) se aplica a la vez en la web y en la API.
- **Lista blanca de campos** (`VacantePublicaResource`): lo que no está escrito allí no sale, por eso añadir una columna a la base no filtra datos por accidente.
- **Sin sesión ni cookies**: la API no usa el grupo `web`, así que no hay CSRF ni contexto de empresa; el aislamiento entre empresas no depende de ella.
- **Escritura y datos privados** (postular, ver mis postulaciones) quedan para una versión con autenticación por token; hoy se hacen solo desde la aplicación web.

## Pruebas

`tests/Feature/Api/VacantesApiTest.php` cubre: solo vacantes abiertas de empresas activas, lista blanca de campos, filtros, validación (422), detalle, 404 de vacantes no públicas, errores en JSON, límite de peticiones y solo lectura.

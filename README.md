# TalentCloud AI

Plataforma SaaS para gestionar la selección de talento. La IA extrae información de los CV, compara los requisitos de una vacante con las competencias de cada candidato y explica cada coincidencia con el fragmento del CV que la respalda. La decisión es siempre del reclutador: el porcentaje ordena y explica, nunca mueve ni descarta a nadie.

Proyecto de la asignatura *Aplicaciones en la Nube*.

## Qué incluye

**Usuarios:** administrador SaaS, empresa, reclutador y postulante.

**Módulos:** empresas, vacantes, candidatos, CV, competencias, postulaciones, entrevistas, evaluaciones, matching inteligente y dashboard.

**IA:** extracción de información de CV (NLP), embeddings, matching semántico y explicabilidad.

| Pedido del enunciado | Dónde está |
| --- | --- |
| Cuatro roles | `talentcloud/app/Enums/Rol.php` |
| Módulos de la aplicación | `talentcloud/app/Livewire/` |
| Extracción de datos del CV | `talentcloud-ai/src/talentcloud_ai/extraccion/` |
| Embeddings | `talentcloud-ai/src/talentcloud_ai/embeddings/` |
| Matching semántico | `talentcloud-ai/src/talentcloud_ai/matching/` |
| Explicación de coincidencias y diferencias | `talentcloud-ai/src/talentcloud_ai/explicabilidad/` |
| Evidencia textual para el reclutador | Pantalla `/matching/{postulacion}` de la aplicación |

Si el servicio de IA no responde, la aplicación calcula un análisis básico por palabras clave y lo marca como tal, para que el proceso no se detenga.

## Arquitectura

```
Navegador ──► Laravel 12 + Livewire ──► PostgreSQL 17 + pgvector
                    │
                    ├── cola de trabajos (IA, correos, avisos)
                    │
                    └──► Servicio de IA (FastAPI, sin base de datos)
                         extracción · embeddings · matching · explicación
```

Laravel guarda los datos y envía al servicio el texto del CV, el catálogo de competencias y los requisitos. El servicio devuelve el resultado y Laravel lo guarda. Cada empresa solo ve sus datos (multi-tenant a nivel de aplicación, con restricciones adicionales en la base).

## Carpetas

| Carpeta | Contenido |
| --- | --- |
| `talentcloud/` | Aplicación Laravel 12 (PHP 8.2, Livewire 3, Tailwind 3) |
| `talentcloud-ai/` | Servicio de IA en Python 3.11 y FastAPI |
| `.github/workflows/` | Pruebas automáticas de ambos proyectos |

Cada carpeta tiene su propio `README.md` con más detalle.

## Cómo ejecutarlo

Requisitos: PHP 8.2 (con `pdo_pgsql`, `intl`, `zip`, `gd`), Composer 2, Node 20 o superior, Docker y [uv](https://docs.astral.sh/uv/) para el servicio de IA.

```bash
cd talentcloud
docker compose up -d              # PostgreSQL + pgvector (puerto 5433) y Mailpit
cp .env.example .env              # completar DB_PASSWORD
composer install && npm install
php artisan key:generate
php artisan migrate:fresh --seed  # estructura y datos de demostración
composer dev                      # http://localhost:8000
```

Para activar la IA: `(cd ../talentcloud-ai && uv sync --extra ml)` y, en el `.env`, `IA_HABILITADA=true` y `MATCHING_ENGINE=http`. La primera vez se descarga el modelo (unos 470 MB). Sin ella, todo funciona con el análisis básico.

Los correos que envía la aplicación se ven en http://localhost:8025 (Mailpit).

## Cuentas de demostración

Todas usan la contraseña `password`. Los datos son ficticios.

| Cuenta | Rol |
| --- | --- |
| `admin@talentcloud.ai` | Administrador SaaS |
| `admin@nimbus.com` | Empresa (Nimbus Corp) |
| `marta@nimbus.com` | Reclutador (Nimbus Corp) |
| `marco@correo.com` | Postulante |

La lista completa está en [`talentcloud/README.md`](talentcloud/README.md#datos-de-demostración).

## Base de datos

- Migraciones: `talentcloud/database/migrations/` (45).
- Volcados de PostgreSQL en `talentcloud/database/esquema/`:
  - `talentcloud_esquema.sql`: solo la estructura (33 tablas).
  - `talentcloud_completa.sql`: estructura y datos de demostración.
- Cómo restaurarlos: [`talentcloud/database/esquema/LEEME.md`](talentcloud/database/esquema/LEEME.md).

## Pruebas y calidad

```bash
cd talentcloud && composer check      # Pint + Larastan (nivel 6) + Pest
cd talentcloud-ai && uv run pytest    # + ruff y mypy
```

La aplicación tiene más de 800 pruebas automáticas y el servicio de IA, 149. El recorrido completo en navegador real está en `talentcloud/tests/e2e/`.

## Pagos

Los planes de pago usan Stripe en **modo de prueba**; no se cobra dinero real. Sin las claves de Stripe, la empresa solicita el plan y el administrador SaaS lo aplica desde «Empresas». Más detalle en [`talentcloud/README.md`](talentcloud/README.md#pagos-con-stripe).

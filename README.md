# TalentCloud AI

Plataforma SaaS para gestionar la selección de talento: la empresa publica vacantes con sus requisitos, recibe postulaciones, revisa los currículos frente a esos requisitos, agenda entrevistas, registra evaluaciones y decide. El sistema ayuda y muestra su evidencia; la decisión es siempre del reclutador.

Proyecto de la asignatura *Aplicaciones en la Nube*. Este repositorio corresponde a la **unidad I**: aplicación web completa y funcional en Laravel, con base de datos real, API REST inicial y despliegue en un servidor virtual. La comparación semántica con inteligencia artificial, los microservicios y el despliegue en más de una nube se trabajan en las unidades II y III.

- Demostración en vivo: https://talentcloudia.online/
- API REST de vacantes abiertas: https://talentcloudia.online/api/v1/vacantes (documentación en [`talentcloud/docs/API.md`](talentcloud/docs/API.md))

## Qué incluye

**Usuarios:** administrador SaaS, empresa, reclutador y postulante.

**Módulos:** empresas y planes, vacantes con requisitos, candidatos, CV, catálogo de competencias, postulaciones en un tablero por etapas, entrevistas, evaluaciones, notas internas, portal y bolsa pública de empleos, avisos por correo y campana, exportación a Excel y paneles (dashboard) según el perfil.

**Análisis de requisitos de la unidad I (sin IA):** el sistema extrae el texto de cada CV (PDF o Word) y busca en él cada competencia pedida por la vacante, o sus sinónimos del catálogo. Para cada requisito indica si aparece, con el fragmento del CV como evidencia, y avisa de los obligatorios que faltan. Es una ayuda para revisar: que una competencia no aparezca no significa que la persona no la tenga, y el sistema nunca mueve ni descarta a nadie.

| Pedido del enunciado | Dónde está |
| --- | --- |
| Cuatro roles | `talentcloud/app/Enums/Rol.php` y `talentcloud/app/Policies/` |
| Módulos de la aplicación | `talentcloud/app/Livewire/` |
| Extracción del texto del CV | `talentcloud/app/Services/Cvs/Extraccion/` |
| Análisis de requisitos con evidencia textual | `talentcloud/app/Services/Postulaciones/AnalisisBasicoDeRequisitos.php` |
| Aislamiento entre empresas (multi-tenant) | `talentcloud/app/Tenancy/` |
| API REST inicial | `talentcloud/routes/api.php` y `talentcloud/docs/API.md` |

## Arquitectura

```
Navegador ──HTTPS──► Caddy ──► Laravel 12 + Livewire ──► PostgreSQL 17
                                  │
                                  ├── cola de trabajos (lectura de CV, correos, avisos)
                                  └── planificador (tareas diarias, avisos de vencimiento de planes)
```

Capas: pantalla (Livewire) → servicio (reglas de negocio) → repositorio → modelo. Cada empresa solo ve sus datos: la aplicación filtra por empresa en cada consulta y la base de datos impide relacionar filas de empresas distintas.

## Organización del equipo

El trabajo se organizó con una adaptación de Scrum; el reparto de tareas y el backlog por sprint están en [`TAREAS.md`](TAREAS.md).

## Carpetas

| Carpeta | Contenido |
| --- | --- |
| `talentcloud/` | Aplicación Laravel 12 (PHP 8.2, Livewire 3, Tailwind 3) |
| `deploy/` | Despliegue en un servidor con Docker Compose y copias de seguridad |
| `.github/workflows/` | Pruebas automáticas y construcción de imágenes |

Cada carpeta tiene su propio `README.md` con más detalle.

## Cómo ejecutarlo

Requisitos: PHP 8.2 (con `pdo_pgsql`, `intl`, `zip`, `gd` y OPcache), Composer 2, Node 22 y Docker.

```bash
cd talentcloud
docker compose up -d              # PostgreSQL 17 (puerto 5433) y Mailpit
cp .env.example .env              # completar DB_PASSWORD
composer install && npm install
php artisan key:generate
php artisan migrate:fresh --seed  # estructura y datos de demostración
composer dev                      # http://localhost:8000
```

Los correos que envía la aplicación se ven en http://localhost:8025 (Mailpit).

## Cuentas de demostración

Todas usan la contraseña `password` en local. Los datos son sintéticos: personas, empresas, teléfonos, correos y CV son ficticios.

| Cuenta | Rol |
| --- | --- |
| `admin@talentcloud.ai` | Administrador SaaS |
| `admin@nimbus.com` | Empresa (Nimbus Corp) |
| `marta@nimbus.com` | Reclutador (Nimbus Corp) |
| `marco@correo.com` | Postulante |

La lista completa está en [`talentcloud/README.md`](talentcloud/README.md#datos-de-demostración). Las cuentas del servidor desplegado tienen otras contraseñas.

## Base de datos

- 31 tablas y 54 migraciones: `talentcloud/database/migrations/`.
- Volcados de PostgreSQL en `talentcloud/database/esquema/`:
  - `talentcloud_esquema.sql`: solo la estructura.
  - `talentcloud_completa.sql`: estructura y datos de demostración.
- Cómo restaurarlos: [`talentcloud/database/esquema/LEEME.md`](talentcloud/database/esquema/LEEME.md).

## API REST

Rutas públicas de solo lectura bajo `/api/v1`: `GET /salud`, `GET /vacantes` (con búsqueda, filtro por modalidad y paginación) y `GET /vacantes/{id}`. Límite de 60 peticiones por minuto por dirección IP.

```bash
curl "https://talentcloudia.online/api/v1/vacantes?modalidad=remoto&por_pagina=2"
```

## Despliegue

Pila para un servidor con Docker (aplicación, cola, planificador, base de datos y HTTPS automático) en [`deploy/`](deploy/). La guía paso a paso, incluidas las copias de seguridad, está en [`deploy/README.md`](deploy/README.md).

## Pruebas y calidad

```bash
cd talentcloud && composer check      # Pint + Larastan (nivel 6) + Pest
```

La aplicación tiene 337 pruebas automáticas contra PostgreSQL. Además hay scripts de Playwright para recorridos en navegador real en `talentcloud/tests/e2e/`.

## Pagos

Los planes de pago usan Stripe en **modo de prueba**; no se cobra dinero real. Con la pasarela apagada (`PAGOS_PASARELA` vacío, como en esta unidad), la empresa solicita el plan y el administrador SaaS lo aplica desde «Empresas». Más detalle en [`talentcloud/README.md`](talentcloud/README.md#pagos-con-stripe-unidad-3).

## Próximas unidades

- **Unidad II:** comparación semántica con inteligencia artificial como servicio independiente, API documentada con al menos dos servicios y contenedores para todos los componentes.
- **Unidad III:** despliegue en Google Cloud y en una segunda plataforma, seguridad, observabilidad y evaluación.

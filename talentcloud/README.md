# TalentCloud AI — aplicación Laravel

SaaS de reclutamiento y selección con matching semántico explicable. Plan completo en `../PLAN_DE_DESARROLLO.md`; referencia visual y de comportamiento en `../TalentCloud AI Platform/`.

Stack: Laravel 12 · PHP 8.2 · Livewire 3 (Volt) + Alpine · Tailwind CSS 3 · PostgreSQL 17 + pgvector · Pest · Pint · Larastan.

## Requisitos

PHP 8.2 con `pdo_pgsql`, `intl`, `zip`, `gd` y **OPcache activo** (en XAMPP viene desactivado: en `php.ini`, `zend_extension=opcache`, `opcache.enable=1` y `opcache.revalidate_freq=0`; sin él cada página tarda segundos); Composer 2; Node 20+; Docker Desktop; Python 3.11 y
[uv](https://docs.astral.sh/uv/) para el servicio de IA (`../talentcloud-ai`).

## Puesta en marcha

```bash
docker compose up -d          # PostgreSQL + pgvector (puerto 5433) y Mailpit
cp .env.example .env          # completar DB_PASSWORD
composer install && npm install
php artisan key:generate
php artisan migrate
(cd ../talentcloud-ai && uv sync --extra ml)   # servicio de IA con el modelo de embeddings
composer dev                  # servidor, cola (ia, default), Vite y servicio de IA (puerto 8001)
```

Servicio de IA: con `IA_HABILITADA=true` y `MATCHING_ENGINE=http`, el matching lo calcula el servicio (coincidencia
exacta + similitud semántica, con evidencia y motivo); si no responde, se usa el análisis básico y
`php artisan talentcloud:recalcular-matching --solo-basicos` lo rehace cuando vuelve. Además, cada CV procesado se analiza (competencias, años, fragmentos con
embedding) y cada cambio de requisitos genera sus embeddings, en la cola `ia`. `IA_API_KEY` debe coincidir con
`TALENTCLOUD_AI_API_KEY` de `talentcloud-ai/.env`. Con `IA_HABILITADA=false` todo funciona igual con el análisis
básico. La primera vez, el servicio descarga el modelo (unos 470 MB).

- App: http://localhost:8000
- Correos capturados (Mailpit): http://localhost:8025

## Datos de demostración

`php artisan migrate:fresh --seed` carga los datos del prototipo reproduciendo la historia de cada postulación
con fechas relativas al día de la carga. Con el servicio de IA en marcha (`composer dev`), analiza los CV y
calcula el matching durante la carga; si la IA no responde, avisa y siembra con el análisis básico, y después
basta con `php artisan talentcloud:reindexar-embeddings --sync` y `php artisan talentcloud:recalcular-matching`.
Todas las cuentas usan la contraseña `password`:

| Cuenta | Rol | Empresa |
| --- | --- | --- |
| admin@talentcloud.ai | Admin SaaS | — |
| admin@nimbus.com | Empresa | Nimbus Corp |
| marta@nimbus.com | Reclutador | Nimbus Corp |
| diego@nimbus.com | Reclutador | Nimbus Corp |
| admin@vela.studio | Empresa | Vela Studio |
| clara@vela.studio | Reclutador | Vela Studio |
| admin@orbita.dev | Empresa | Orbita Labs (suspendida: no puede ingresar) |
| marco@correo.com | Postulante | — |

El postulante ve las vacantes abiertas de las empresas activas en «Vacantes» y postula con su CV; la empresa recibe el aviso y la postulación entra en su Kanban con el matching de la IA. También puede retirar una postulación en curso, editar su titular y teléfono en «Mi perfil» y descargar sus datos en JSON. Sin iniciar sesión, cualquiera ve las vacantes abiertas en `/empleos` (y las más recientes en la presentación); «Postular» lleva a iniciar sesión o registrarse y vuelve a la vacante.

La empresa ve en el dashboard el embudo de selección de los últimos 90 días, y en «Mi empresa» el uso de su plan: vacantes activas, usuarios y postulaciones evaluadas con IA en el mes (Gratis: 1, 2 y 30; Starter: 3, 3 y 300; Pro: 15, 10 y 2 000; Enterprise: sin límite). Toda empresa nueva entra en Gratis con 14 días del plan Pro. Los precios están en `/planes`; la empresa solicita un plan desde «Mi empresa» y el admin SaaS lo aplica desde «Empresas» (todavía sin pago en línea).

`docker compose` toma `DB_PASSWORD` del `.env`. Crea las bases `talentcloud` y `talentcloud_testing`, ambas con la extensión `vector`.

## Calidad

```bash
composer check                # Pint --test + Larastan (nivel 6) + Pest
composer lint                 # Pint (corrige formato)
composer analyse              # Larastan
php artisan test --filter=NombreDelTest
```

Los tests usan PostgreSQL (`talentcloud_testing`), no SQLite, porque el esquema usa pgvector.

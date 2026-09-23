# TalentCloud AI — aplicación Laravel

SaaS de reclutamiento y selección con matching semántico explicable. Plan completo en `../PLAN_DE_DESARROLLO.md`; referencia visual y de comportamiento en `../TalentCloud AI Platform/`.

Stack: Laravel 12 · PHP 8.2 · Livewire 3 (Volt) + Alpine · Tailwind CSS 3 · PostgreSQL 17 + pgvector · Pest · Pint · Larastan.

## Requisitos

PHP 8.2 con `pdo_pgsql`, `intl`, `zip`, `gd`; Composer 2; Node 20+; Docker Desktop.

## Puesta en marcha

```bash
docker compose up -d          # PostgreSQL + pgvector (puerto 5433) y Mailpit
cp .env.example .env          # completar DB_PASSWORD
composer install && npm install
php artisan key:generate
php artisan migrate
composer dev                  # servidor, cola (ia, default) y Vite
```

- App: http://localhost:8000
- Correos capturados (Mailpit): http://localhost:8025

## Datos de demostración

`php artisan migrate:fresh --seed` carga los datos del prototipo. Todas las cuentas usan la contraseña `password`:

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

`docker compose` toma `DB_PASSWORD` del `.env`. Crea las bases `talentcloud` y `talentcloud_testing`, ambas con la extensión `vector`.

## Calidad

```bash
composer check                # Pint --test + Larastan (nivel 6) + Pest
composer lint                 # Pint (corrige formato)
composer analyse              # Larastan
php artisan test --filter=NombreDelTest
```

Los tests usan PostgreSQL (`talentcloud_testing`), no SQLite, porque el esquema usa pgvector.

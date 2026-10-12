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

`php artisan migrate:fresh --seed` carga siete empresas (tecnología, diseño, retail, logística, salud y gastronomía), 15 vacantes y 42 postulaciones con su CV, reproduciendo la historia de cada postulación
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
| admin@brioretail.pe | Empresa | Brío Retail |
| rocio@brioretail.pe | Reclutador | Brío Retail |
| admin@rutasur.pe | Empresa | Ruta Sur Logística |
| jorge@rutasur.pe | Reclutador | Ruta Sur Logística |
| admin@nunasalud.pe | Empresa | Nuna Salud (pidió el plan Enterprise) |
| patricia@nunasalud.pe | Reclutador | Nuna Salud |
| admin@alturacafe.pe | Empresa | Altura Café (registrada hace 5 días, en la prueba del plan Pro) |
| marco@correo.com | Postulante | — |

Otros postulantes con cuenta: renzo.paredes, milagros.huaman (retiró su postulación), gianella.torres, luis.chavez,
karen.espinoza, kevin.morales, mariana.alarcon, lucero.mamani y thiago.huillca, todos `@correo.com`.
El Libro de Reclamaciones tiene una hoja respondida y otra pendiente.

El postulante ve las vacantes abiertas de las empresas activas en «Vacantes» y postula con su CV; la empresa recibe el aviso y la postulación entra en su Kanban con el matching de la IA. También puede retirar una postulación en curso, editar su titular y teléfono en «Mi perfil» y descargar sus datos en JSON. Sin iniciar sesión, cualquiera ve las vacantes abiertas en `/empleos` (y las más recientes en la presentación); «Postular» lleva a iniciar sesión o registrarse y vuelve a la vacante.

La empresa ve en el dashboard el embudo de selección de los últimos 90 días, y en «Mi empresa» el uso de su plan: vacantes activas, usuarios y postulaciones evaluadas con IA en el mes (Gratis: 1, 2 y 30; Starter: 3, 3 y 300; Pro: 15, 10 y 2 000; Enterprise: sin límite). Toda empresa nueva entra en Gratis con 14 días del plan Pro. Los precios están en `/planes`. Con la pasarela configurada (`PAGOS_PASARELA=stripe`, ver «Pagos con Stripe»), Starter y Pro se pagan con tarjeta desde «Mi empresa»; sin ella, y para Enterprise, la empresa solicita el plan y el admin SaaS lo aplica desde «Empresas».

`docker compose` toma `DB_PASSWORD` del `.env`. Crea las bases `talentcloud` y `talentcloud_testing`, ambas con la extensión `vector`.

## Pagos con Stripe

La empresa es el cliente de Stripe. «Pagar» en «Mi empresa» abre Stripe Checkout (precio en soles más IGV) y
el plan cambia **solo** cuando llega el aviso firmado de Stripe a `POST /stripe/webhook`: la suscripción se
relee de Stripe en cada aviso, así que un aviso repetido o desordenado da el mismo resultado. Cambiar de plan,
la tarjeta o cancelar se hace en el portal de clientes de Stripe («Gestionar suscripción»). Un cobro fallido
mantiene el plan mientras Stripe reintenta y avisa a la cuenta de empresa; si la suscripción termina, la
empresa pasa a Gratis sin perder nada. Código en `app/Pagos` (puerto `PasarelaDePago`) y `app/Services/Pagos`.

En el panel de Stripe (modo de prueba): los productos Starter y Pro con un precio mensual y otro anual en PEN
sin IGV, una tasa de impuesto IGV del 18 % excluida del precio, el portal de clientes (tarjeta, facturas,
cambio entre Starter y Pro **sin** permitir cambiar la cantidad, cancelación al final del periodo) y, en pagos fallidos, cancelar la suscripción
cuando se agoten los reintentos. Sus identificadores van en el `.env` (ver `.env.example`).

En local, para recibir los avisos (el `whsec_...` que muestra va en `STRIPE_WEBHOOK_SECRET`):

```bash
stripe listen --events checkout.session.completed,customer.subscription.created,customer.subscription.updated,customer.subscription.deleted,invoice.payment_failed --forward-to http://127.0.0.1:8000/stripe/webhook
```

Tarjetas de prueba: `4242 4242 4242 4242` (aprobada), `4000 0000 0000 9995` (fondos insuficientes),
`4000 0025 0000 3155` (pide 3D Secure); cualquier fecha futura y CVC. En producción se crea el endpoint
`https://<dominio>/stripe/webhook` en el panel de Stripe con esos mismos eventos y su secreto va en
`STRIPE_WEBHOOK_SECRET`. Los recibos de Stripe no son comprobantes electrónicos de SUNAT.

## Calidad

```bash
composer check                # Pint --test + Larastan (nivel 6) + Pest
composer lint                 # Pint (corrige formato)
composer analyse              # Larastan
php artisan test --filter=NombreDelTest
```

Los tests usan PostgreSQL (`talentcloud_testing`), no SQLite, porque el esquema usa pgvector.
`tests/Feature/Rendimiento` fija cuántas consultas hace cada página: si una supera su presupuesto, buscar la
relación que se carga dentro de un bucle (N+1) antes de subir la cifra.

## Rendimiento en producción

En la nube cada consulta viaja por la red hasta la base de datos, así que importan sobre todo cuántas consultas
hace una página y que el arranque de Laravel no relea su configuración en cada petición.

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize          # configuración, rutas, eventos, vistas e iconos en caché
```

- Después de `optimize`, `env()` solo funciona dentro de `config/` (lo vigila un test): toda variable nueva se
  lee con `config()`.
- PHP con OPcache (`opcache.enable=1`, `opcache.validate_timestamps=0` en producción: tras cada despliegue,
  reiniciar PHP-FPM) y compresión gzip en el servidor web.
- Cloud SQL en la misma región que la aplicación y por IP privada. Sesiones, caché y colas siguen en la base
  de datos (compartidas si hay más de un servidor); con un solo servidor, `PERMISSION_CACHE_STORE=file` evita
  una lectura por petición.
- Medido con 300 000 postulaciones (base sintética): los listados y el dashboard de una empresa responden en
  decenas de milisegundos gracias a los índices por empresa. El embudo de toda la plataforma (admin SaaS) tarda
  unos 300 ms; si el volumen real se acerca a esa cifra, guardar en la postulación la etapa más avanzada
  alcanzada en lugar de calcularla del historial.

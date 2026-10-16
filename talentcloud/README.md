# TalentCloud AI — aplicación Laravel

SaaS de reclutamiento y selección con matching semántico explicable. Visión general del proyecto en el [README de la raíz](../README.md).

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

### Volumen de demostración (opcional)

La demo base tiene pocas postulaciones por vacante. Para ver el producto «lleno» (dashboard, ranking, Kanban con
«Ver más», Excel con datos), después de `migrate:fresh --seed`:

```bash
php artisan db:seed --class=VolumenDeDemostracionSeeder     # unos 15 min; DEMO_ESCALA_DE_VOLUMEN=0.1 para una prueba rápida
```

Lleva cada empresa a unas diez vacantes (las antiguas cerradas con su contratación, las recientes abiertas, respetando el
límite de vacantes activas de cada plan) y siembra ~3 500 postulaciones de personas con un CV como el de César Polo
(perfil, experiencia con fechas, proyectos, educación, habilidades). El volumen depende del tipo de puesto: cientos en
contratación masiva (cajeros, operarios, mozos), decenas en perfiles técnicos y pocas en jefaturas. Reproduce la historia
con los servicios de la aplicación (registro, CV, postulación, Kanban, entrevistas, evaluaciones, cierre), así que el
historial y la actividad son los de un uso real. El matching es el análisis básico (no gasta el cupo de IA); después:
`php artisan talentcloud:reindexar-embeddings --sync` y `talentcloud:recalcular-matching` para lo que quieras mostrar con IA.
No es parte de `DatabaseSeeder` (los tests no lo necesitan). Orbita Labs, que está suspendida, no recibe vacantes.

### Escala

Medido sobre una copia con una sola empresa de 10 000 vacantes, ~120 000 postulaciones y una vacante con 15 000
(`database/rendimiento/carga_empresa_grande.sql`): Selección 0,45 s y 36 MB (antes 20 s y caída por memoria), matching
0,4 s, «Programar entrevista» y «Evaluar» 20 ms (antes caída y 3,4 s). Lo que lo permite: el tablero trae las primeras 25
tarjetas de cada columna («Ver más»), el ranking se pagina en SQL con el resumen de cumplimiento en columnas indexadas
(`postulaciones.requisitos_total/_cubiertos/obligatorios_faltantes`, que escribe `CalcularMatch`), mover una tarjeta
renumera en SQL con un candado sobre la vacante, los desplegables enormes son buscadores (`<x-ui.buscador>`: 20 resultados
por consulta) y las cifras agregadas del dashboard se guardan 60 s por empresa (`DASHBOARD_CACHE_SEGUNDOS`).

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

### Probar los pagos

- **Automáticas (`composer check`):** `PagosEnLineaTest` y `PasarelaStripeTest` cubren el pago confirmado, el cobro fallido y sus
  reintentos, la cancelación, los avisos repetidos o tardíos y la firma alterada o vencida, con una pasarela falsa.
- **Contra el entorno de pruebas real de Stripe (opt-in, con red):** `STRIPE_LIVE_TESTS=1 php artisan test --filter=StripeEnModoDePrueba`.
  Usa las claves de prueba del `.env`, se niega a correr con una que no sea `sk_test_`, crea clientes y suscripciones sintéticos
  (`example.com`) y los borra al terminar. El aviso se firma localmente con `STRIPE_WEBHOOK_SECRET` y se envía a `/stripe/webhook`:
  no hace falta túnel. Si una ejecución se interrumpe, los clientes huérfanos llevan `metadata.origen = auditoria-talentcloud`.
- **A mano, de extremo a extremo** (lo único que no cubre lo anterior: la página de Checkout de Stripe y la entrega real del aviso):
  1. `stripe login` y `stripe listen --forward-to localhost:8000/stripe/webhook` (la CLI imprime el `whsec_…` que va en `.env`).
  2. En «Mi empresa» → «Pagar» con `4242 4242 4242 4242`: debe volver a «Mi empresa», confirmar el pago y cambiar el plan.
  3. Repetir con `4000 0000 0000 0341` (se adjunta pero el cobro falla) y `4000 0025 0000 3155` (3D Secure).
  4. `stripe trigger invoice.payment_failed` y `stripe trigger customer.subscription.deleted` para ver el aviso de cobro fallido y el paso a Gratis.

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
- Cloud SQL en la misma región que la aplicación y por IP privada. **Sesión y caché fuera de PostgreSQL**
  (`SESSION_DRIVER` y `CACHE_STORE`): con `database`, cada petición web y cada acción de Livewire suma 2–4 viajes
  a la base solo para la sesión, la caché de roles y las cifras en caché. Con un solo servidor y en local, `file`
  (el valor de `.env.example`); con varios servidores, Redis (Memorystore, `REDIS_*`, extensión `phpredis`), porque
  la caché y la sesión deben ser compartidas. Las colas pueden seguir en `database` mientras el volumen sea bajo.
  Lo que se guarda en caché (estado de la empresa, catálogo de competencias, cifras del dashboard) se invalida al
  guardar el modelo y tiene vigencia corta, así que suspender una empresa surte efecto en la petición siguiente.
- Medido con 300 000 postulaciones (base sintética): los listados y el dashboard de una empresa responden en
  decenas de milisegundos gracias a los índices por empresa. El embudo de toda la plataforma (admin SaaS) tarda
  unos 300 ms; si el volumen real se acerca a esa cifra, guardar en la postulación la etapa más avanzada
  alcanzada en lugar de calcularla del historial.

# Recorrido en navegador real (Playwright)

Comprueba lo que las pruebas de Pest no pueden: JavaScript (Alpine/Livewire), interacciones (puntero y teclado del
gráfico, diálogos, descargas), el aspecto responsivo y las cabeceras de seguridad con un Chrome de verdad.
Son 30 pasos: empresa (dashboard, gráfico, Kanban, mover, matching, entrevistas, `.ics`, acceso entre empresas),
móvil de 390 px (sin desplazamiento horizontal), postulante (calendario sin datos internos, retirar postulación),
admin y bloqueo del iframe.

Hay dos guiones: `recorrido.js` (flujos, 30 pasos) e `interaccion.js` (13 pasos: hover y presionado, estados de carga con la red
ralentizada, «reducir movimiento» y estados vacíos). Los dos pasan contra una base recién sembrada.

**No es parte de `composer check` ni del CI**: es una verificación manual antes de integrar o desplegar. No añade
dependencias al proyecto.

## Requisitos
- Node y `playwright-core` (`npm i --no-save playwright-core` en una carpeta aparte, o el que ya tenga tu equipo) y Chrome instalado.
- Una **base nueva y aislada**: el recorrido modifica datos (mueve tarjetas, programa una entrevista, retira una postulación).
  Nunca contra una base que quieras conservar.

## Cómo ejecutarlo
```bash
# 1. Base aislada con la demostración (desde talentcloud/)
docker exec talentcloud-pgsql psql -U postgres -c "CREATE DATABASE talentcloud_e2e"
DB_DATABASE=talentcloud_e2e php artisan migrate --force
IA_HABILITADA=false MATCHING_ENGINE=fake DB_DATABASE=talentcloud_e2e php artisan db:seed --force

# 2. Servidor aparte (otro puerto: no toca tu `composer dev`)
cd public
DB_DATABASE=talentcloud_e2e IA_HABILITADA=false QUEUE_CONNECTION=sync MAIL_MAILER=log \
APP_URL=http://127.0.0.1:8099 DASHBOARD_CACHE_SEGUNDOS=0 \
php -S 127.0.0.1:8099 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php

# 3. El recorrido (los assets los sirve Vite: `npm run dev` o `npm run build`)
PLAYWRIGHT_CORE=/ruta/a/node_modules/playwright-core node tests/e2e/recorrido.js
PLAYWRIGHT_CORE=/ruta/a/node_modules/playwright-core node tests/e2e/interaccion.js   # no modifica datos
```
Variables: `E2E_BASE` (por defecto `http://127.0.0.1:8099`), `PLAYWRIGHT_CORE`, `E2E_CHROME` (ejecutable de Chromium si no
quieres el Chrome del sistema) y `E2E_SALIDA` (capturas de los pasos que fallen y los `.ics` descargados).

## Detalles que conviene saber
- Los identificadores (vacante 7 de Nimbus, 9 de Vela) son los de la siembra de demostración **sobre una base nueva**.
- Las cuentas son las de `README.md` (contraseña `password`).
- El nombre accesible de una tarjeta del Kanban es «Nombre, Etapa» (no solo el nombre); los diálogos son `div[role=dialog]`.
- Tras pulsar un botón que cambia de diálogo, espera el texto del nuevo antes de actuar: Livewire tarda unos milisegundos.
- Termina con código 0 si todo pasa y 2 si algún paso falla.

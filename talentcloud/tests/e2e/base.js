// Utilidades comunes del recorrido en navegador (Playwright directo, sin MCP). Ver README.md.
//   E2E_BASE            dirección de la app aislada (por defecto http://127.0.0.1:8099)
//   PLAYWRIGHT_CORE     ruta a `playwright-core` si no está instalado en el proyecto
//   E2E_CHROME          ejecutable de Chromium/Chrome (por defecto, el Chrome del sistema)
const path = require('path');
const fs = require('fs');
const { chromium } = require(process.env.PLAYWRIGHT_CORE || 'playwright-core');

const BASE = process.env.E2E_BASE || 'http://127.0.0.1:8099';
const SALIDA = process.env.E2E_SALIDA || path.join(__dirname, 'salida');
fs.mkdirSync(SALIDA, { recursive: true });

const CHROME = process.env.E2E_CHROME || null; // null = Chrome instalado en el sistema

async function abrir({ ancho = 1440, alto = 900 } = {}) {
  const navegador = await chromium.launch(CHROME ? { executablePath: CHROME, headless: true } : { channel: 'chrome', headless: true });
  // Contexto nuevo por recorrido (CLAUDE.md: las pestañas viejas dejan de enviar las acciones de Livewire).
  const contexto = await navegador.newContext({ viewport: { width: ancho, height: alto }, acceptDownloads: true, locale: 'es-PE' });
  const pagina = await contexto.newPage();
  const errores = [];
  pagina.on('console', (m) => { if (m.type() === 'error') errores.push('console: ' + m.text().slice(0, 200)); });
  pagina.on('pageerror', (e) => errores.push('pageerror: ' + String(e).slice(0, 200)));
  pagina.on('response', (r) => { if (r.status() >= 500) errores.push(`HTTP ${r.status()} ${r.url().slice(0, 120)}`); });
  return { navegador, contexto, pagina, errores };
}

async function iniciarSesion(pagina, correo, clave = 'password') {
  await pagina.goto(BASE + '/login', { waitUntil: 'load' });
  await pagina.waitForSelector('input[type="email"]', { state: 'visible', timeout: 15000 });
  await pagina.fill('input[type="email"]', correo);
  await pagina.fill('input[type="password"]', clave);
  await Promise.all([
    pagina.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 20000 }),
    pagina.keyboard.press('Enter'),
  ]);
}

const resultados = [];
async function paso(nombre, pagina, fn) {
  const t0 = Date.now();
  try {
    await fn();
    resultados.push({ nombre, ok: true, ms: Date.now() - t0 });
    console.log(`  OK   ${nombre} (${Date.now() - t0} ms)`);
  } catch (e) {
    resultados.push({ nombre, ok: false, error: String(e.message || e).split('\n')[0].slice(0, 240) });
    console.log(`  FALLA ${nombre}: ${String(e.message || e).split('\n')[0].slice(0, 240)}`);
    try { await pagina.screenshot({ path: path.join(SALIDA, nombre.replace(/[^a-z0-9]+/gi, '_') + '.png') }); } catch {}
  }
}

module.exports = { abrir, iniciarSesion, paso, resultados, BASE, SALIDA };

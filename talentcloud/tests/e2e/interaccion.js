// Hover, carga (con la red ralentizada), «reducir movimiento» y estados vacíos, en un navegador real. Ver README.md.
// Necesita la demostración sembrada en una base NUEVA (usa Altura Café, la empresa sin datos).
const path = require('path');
const { abrir, iniciarSesion, paso, resultados, BASE, SALIDA } = require('./base');

const afirmar = (c, m) => { if (!c) throw new Error(m); };
const foto = (pagina, nombre) => pagina.screenshot({ path: path.join(SALIDA, nombre + '.png') });
const estilo = (loc, prop) => loc.evaluate((el, p) => getComputedStyle(el)[p], prop);

(async () => {
  console.log('\n== Hover y presionado (puntero) ==');
  let { navegador, pagina } = await abrir({ ancho: 1440, alto: 900 });
  await iniciarSesion(pagina, 'marta@nimbus.com');

  await paso('H1 tarjeta del Kanban: sube 1 px, gana sombra y marco, y el cursor es de agarre', pagina, async () => {
    await pagina.goto(BASE + '/seleccion/7', { waitUntil: 'load' });
    const tarjeta = pagina.locator('[data-postulacion]').first();
    const reposo = { t: await estilo(tarjeta, 'transform'), s: await estilo(tarjeta, 'boxShadow'), c: await estilo(tarjeta, 'cursor') };
    await tarjeta.hover();
    await pagina.waitForTimeout(400);
    const hover = { t: await estilo(tarjeta, 'transform'), s: await estilo(tarjeta, 'boxShadow') };
    afirmar(reposo.t === 'none', 'en reposo no debe haber transformación: ' + reposo.t);
    afirmar(/matrix\(1, 0, 0, 1, 0, -1\)/.test(hover.t), 'al pasar el puntero debe subir 1 px: ' + hover.t);
    afirmar(hover.s !== reposo.s, 'la sombra no cambió');
    afirmar(reposo.c === 'grab', 'el cursor debería ser grab: ' + reposo.c);
    await foto(pagina, 'despues_hover_tarjeta');
  });

  await paso('H2 las transiciones son cortas (140 ms) y no hay rebote', pagina, async () => {
    const tarjeta = pagina.locator('[data-postulacion]').first();
    const d = await estilo(tarjeta, 'transitionDuration');
    afirmar(d.split(',').every((x) => x.trim() === '0.14s'), 'duración inesperada: ' + d);
    const f = await estilo(tarjeta, 'transitionTimingFunction');
    afirmar(!f.includes('cubic-bezier'), 'no debe haber curva con rebote: ' + f);
  });

  await paso('H3 un botón responde al hover (color) y al pulsar (baja 1 px)', pagina, async () => {
    await pagina.goto(BASE + '/entrevistas', { waitUntil: 'load' });
    const boton = pagina.getByRole('button', { name: /Programar entrevista/ }).first();
    const antes = await estilo(boton, 'backgroundColor');
    await boton.hover();
    await pagina.waitForTimeout(350);
    const sobre = await estilo(boton, 'backgroundColor');
    afirmar(antes !== sobre, 'el botón no cambia de color en hover');
    await pagina.mouse.down();
    await pagina.waitForTimeout(250);
    const t = await estilo(boton, 'transform');
    await pagina.mouse.up();
    afirmar(/matrix\(1, 0, 0, 1, 0, 1\)/.test(t), 'al pulsar debe bajar 1 px: ' + t);
  });

  // Los indicadores y el periodo son del panel de la cuenta de empresa (la reclutadora ve «Mi día» desde el 22-10).
  await navegador.close();
  ({ navegador, pagina } = await abrir({ ancho: 1440, alto: 900 }));
  await iniciarSesion(pagina, 'admin@nimbus.com');

  await paso('H4 el indicador pulsable del dashboard se realza y el que no se pulsa, no', pagina, async () => {
    await pagina.goto(BASE + '/dashboard', { waitUntil: 'load' });
    const pulsable = pagina.locator('button.tarjeta-clicable').first();
    if (await pulsable.count()) {
      await pulsable.hover(); await pagina.waitForTimeout(350);
      afirmar(/-1\)/.test(await estilo(pulsable, 'transform')), 'el indicador pulsable no se realza');
    }
    const fijo = pagina.locator('div.card', { hasText: 'Vacantes activas' }).first();
    await fijo.hover(); await pagina.waitForTimeout(350);
    afirmar((await estilo(fijo, 'transform')) === 'none', 'un indicador que no se pulsa no debe moverse');
  });
  await navegador.close();

  console.log('\n== Carga (red ralentizada a 1,6 s por petición de Livewire) ==');
  ({ navegador, pagina } = await abrir({ ancho: 1440, alto: 900 }));
  await iniciarSesion(pagina, 'admin@nimbus.com'); // la cuenta de empresa: busca, programa y tiene el periodo del panel
  await pagina.route('**/livewire/update', async (ruta) => { await new Promise((r) => setTimeout(r, 1600)); await ruta.continue(); });

  await paso('C1 al buscar, la lista se atenúa, aparece la barra superior y todo vuelve al terminar', pagina, async () => {
    await pagina.goto(BASE + '/candidatos', { waitUntil: 'load' });
    await pagina.getByPlaceholder('Buscar candidato…').fill('Elena');
    await pagina.waitForSelector('.tc-actualizando', { timeout: 3000 });
    await pagina.waitForSelector('html[data-ocupado]', { timeout: 3000 });
    const op = await estilo(pagina.locator('.tc-actualizando').first(), 'opacity');
    afirmar(Number(op) < 0.8, 'la zona no se atenuó: ' + op);
    const barra = await pagina.evaluate(() => { const e = getComputedStyle(document.documentElement, '::before'); return { alto: e.height, pos: e.position, anim: e.animationName }; });
    afirmar(barra.pos === 'fixed' && barra.alto === '2px' && barra.anim === 'tc-barra', 'barra inesperada: ' + JSON.stringify(barra));
    await foto(pagina, 'despues_carga_busqueda');
    await pagina.waitForSelector('.tc-actualizando', { state: 'detached', timeout: 6000 });
    await pagina.waitForSelector('html[data-ocupado]', { state: 'detached', timeout: 3000 });
  });

  await paso('C2 el botón que lanzó la acción muestra el indicador y no admite otro clic', pagina, async () => {
    await pagina.goto(BASE + '/entrevistas', { waitUntil: 'load' });
    const boton = pagina.getByRole('button', { name: /Programar entrevista/ }).first();
    await boton.click();
    await pagina.waitForSelector('.btn[data-cargando]', { timeout: 2000 });
    const giro = await pagina.evaluate(() => { const e = getComputedStyle(document.querySelector('.btn[data-cargando]'), '::before'); return { a: e.animationName, c: e.content }; });
    afirmar(giro.a === 'tc-giro', 'no gira: ' + JSON.stringify(giro));
    afirmar((await estilo(pagina.locator('.btn[data-cargando]').first(), 'pointerEvents')) === 'none', 'admite otro clic');
    await foto(pagina, 'despues_carga_boton');
    await pagina.waitForSelector('.btn[data-cargando]', { state: 'detached', timeout: 6000 });
  });

  await paso('C3 cambiar de periodo en el dashboard atenúa el gráfico y el embudo', pagina, async () => {
    await pagina.goto(BASE + '/dashboard', { waitUntil: 'load' });
    await pagina.getByText('90 días', { exact: true }).first().click();
    await pagina.waitForSelector('.tc-actualizando', { timeout: 3000 });
    afirmar((await pagina.locator('.tc-actualizando').count()) >= 2, 'deberían atenuarse el gráfico y el embudo');
    await foto(pagina, 'despues_carga_periodo');
    await pagina.waitForSelector('.tc-actualizando', { state: 'detached', timeout: 6000 });
  });

  await paso('C4 los sondeos (wire:poll) no encienden la barra superior', pagina, async () => {
    await pagina.goto(BASE + '/dashboard', { waitUntil: 'load' });
    await pagina.waitForTimeout(500);
    await pagina.evaluate(() => { window.__barra = false; new MutationObserver(() => { if (document.documentElement.hasAttribute('data-ocupado')) window.__barra = true; }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-ocupado'] }); });
    await pagina.evaluate(() => window.Livewire.all().find((c) => c.name.includes('actividad-reciente'))?.$wire.$refresh());
    await pagina.waitForTimeout(2600);
    afirmar(await pagina.evaluate(() => window.__barra) === false, 'un refresco sin acción de la persona encendió la barra');
  });
  await navegador.close();

  console.log('\n== Con «reducir movimiento» ==');
  ({ navegador, pagina } = await abrir({ ancho: 1440, alto: 900 }));
  await pagina.emulateMedia({ reducedMotion: 'reduce' });
  await iniciarSesion(pagina, 'marta@nimbus.com');
  await paso('M1 sin transiciones ni desplazamientos', pagina, async () => {
    await pagina.goto(BASE + '/seleccion/7', { waitUntil: 'load' });
    const tarjeta = pagina.locator('[data-postulacion]').first();
    await tarjeta.hover(); await pagina.waitForTimeout(300);
    afirmar((await estilo(tarjeta, 'transform')) === 'none', 'se mueve con reducir movimiento');
    afirmar((await estilo(tarjeta, 'transitionDuration')) === '0s', 'transiciona con reducir movimiento: ' + await estilo(tarjeta, 'transitionDuration'));
  });
  await navegador.close();

  console.log('\n== Estados vacíos (empresa nueva: Altura Café) ==');
  ({ navegador, pagina } = await abrir({ ancho: 1440, alto: 900 }));
  await iniciarSesion(pagina, 'admin@alturacafe.pe');
  await paso('V1 Evaluaciones sin datos: ilustración, título y acción', pagina, async () => {
    await pagina.goto(BASE + '/evaluaciones', { waitUntil: 'load' });
    await pagina.getByRole('heading', { name: 'Aún no hay evaluaciones' }).waitFor({ timeout: 6000 });
    afirmar((await pagina.locator('svg[aria-hidden="true"]').count()) > 0, 'falta la ilustración');
    await foto(pagina, 'despues_vacio_evaluaciones');
  });
  await paso('V2 Evaluaciones con una búsqueda sin resultados NO muestra la bienvenida', pagina, async () => {
    await pagina.getByPlaceholder('Buscar candidato o vacante…').fill('zzzz');
    await pagina.getByText('No se encontraron evaluaciones.').waitFor({ timeout: 6000 });
    afirmar((await pagina.getByText('Aún no hay evaluaciones').count()) === 0, 'mezcla los dos estados');
  });
  await paso('V3 columnas vacías del Kanban', pagina, async () => {
    await pagina.goto(BASE + '/seleccion', { waitUntil: 'load' });
    await pagina.waitForSelector('.columna-vacia', { timeout: 6000 });
    afirmar((await pagina.locator('.columna-vacia').count()) === 4, 'columnas vacías: ' + await pagina.locator('.columna-vacia').count());
    await foto(pagina, 'despues_vacio_kanban');
  });
  await paso('V4 «Todo al día» del dashboard', pagina, async () => {
    await pagina.goto(BASE + '/dashboard', { waitUntil: 'load' });
    await pagina.getByText('Todo al día', { exact: true }).waitFor({ timeout: 6000 });
    await foto(pagina, 'despues_vacio_dashboard');
  });
  await navegador.close();

  const ok = resultados.filter((r) => r.ok).length;
  console.log(`\nRESUMEN: ${ok}/${resultados.length}`);
  for (const r of resultados.filter((x) => !x.ok)) console.log(' - FALLA:', r.nombre, '→', r.error);
  process.exit(ok === resultados.length ? 0 : 2);
})().catch((e) => { console.error('ERROR FATAL', e); process.exit(1); });

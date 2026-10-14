const fs = require('fs');
const path = require('path');
const { abrir, iniciarSesion, paso, resultados, BASE, SALIDA } = require('./base');

const afirmar = (cond, msg) => { if (!cond) throw new Error(msg); };
const tarjeta = (pagina, nombre) => pagina.getByRole('button', { name: new RegExp('^' + nombre + ', ') }).first();
const dialogoVisible = (pagina) => pagina.locator('[role="dialog"]:visible').first();

async function sinDesborde(pagina, url) {
  await pagina.goto(BASE + url, { waitUntil: 'load' });
  await pagina.waitForTimeout(1200);
  const m = await pagina.evaluate(() => ({ cw: document.documentElement.clientWidth, sw: document.documentElement.scrollWidth }));
  afirmar(m.sw <= m.cw + 1, `desborde horizontal en ${url}: scrollWidth ${m.sw} > ${m.cw}`);
}

(async () => {
  // ───────────── EMPRESA (reclutador) ─────────────
  console.log('\n== Empresa: reclutador de Nimbus Corp ==');
  let { navegador, pagina, errores } = await abrir({ ancho: 1440, alto: 1000 });
  await iniciarSesion(pagina, 'marta@nimbus.com');

  await paso('E1 dashboard: encabezado, gráfico y sin errores de consola', pagina, async () => {
    await pagina.goto(BASE + '/dashboard', { waitUntil: 'load' });
    await pagina.getByRole('heading', { name: 'Evolución de postulaciones' }).waitFor({ timeout: 10000 });
    afirmar((await pagina.locator('svg[role="img"]').count()) >= 1, 'no hay gráfico');
    afirmar(errores.length === 0, 'errores: ' + JSON.stringify(errores));
  });

  await paso('E2 gráfico: el puntero muestra los valores (Alpine)', pagina, async () => {
    const zona = pagina.locator('svg[role="img"] rect[fill="transparent"]').nth(14);
    await zona.scrollIntoViewIfNeeded();
    const caja = await zona.boundingBox();
    await pagina.mouse.move(caja.x + caja.width / 2, caja.y + caja.height / 2);
    const tip = pagina.locator('div.pointer-events-none.absolute:visible').filter({ hasText: 'Periodo actual' }).first();
    await tip.waitFor({ state: 'visible', timeout: 4000 });
    const t = (await tip.innerText()).replace(/\s+/g, ' ');
    afirmar(/Periodo actual: \d+/.test(t) && /Periodo anterior: \d+/.test(t), 'tooltip sin valores: ' + t);
  });

  await paso('E3 gráfico: las flechas del teclado recorren los puntos', pagina, async () => {
    await pagina.mouse.move(5, 5);
    const grupo = pagina.locator('div[role="group"][tabindex="0"]').first();
    await grupo.focus();
    await pagina.keyboard.press('ArrowLeft');
    await pagina.keyboard.press('ArrowLeft');
    await pagina.waitForTimeout(300);
    const t = (await pagina.locator('p.sr-only[role="status"]').first().innerText()).replace(/\s+/g, ' ');
    afirmar(/Periodo actual: \d+/.test(t), 'el lector de pantalla no recibió el valor: «' + t + '»');
    afirmar((await pagina.locator('div.pointer-events-none.absolute:visible').filter({ hasText: 'Periodo actual' }).count()) === 1, 'no se ve el valor del punto activo');
    await pagina.keyboard.press('Escape');
    afirmar((await pagina.locator('div.pointer-events-none.absolute:visible').count()) === 0, 'Escape no oculta el valor');
  });

  await paso('E4 el selector de periodo cambia gráfico y embudo (90 días)', pagina, async () => {
    await pagina.getByText('90 días', { exact: true }).first().click();
    await pagina.getByText(/postulaciones? en los últimos 90 días/).first().waitFor({ timeout: 8000 });
    afirmar(pagina.url().includes('periodo=90'), 'la dirección no refleja el periodo: ' + pagina.url());
    afirmar((await pagina.locator('body').innerText()).includes('(90 días)'), 'el embudo no cambió de periodo');
  });

  await paso('E5 atención: «Ir directo a» lleva a Selección', pagina, async () => {
    await pagina.getByRole('link', { name: 'Selección', exact: true }).last().click();
    await pagina.waitForURL(/\/seleccion/, { timeout: 8000 });
  });

  await paso('E6 selección: tablero con sus columnas y nombre accesible de la tarjeta', pagina, async () => {
    await pagina.goto(BASE + '/seleccion/7', { waitUntil: 'load' });
    const cuerpo = (await pagina.locator('body').innerText()).toUpperCase();
    for (const col of ['POSTULADO', 'EN REVISIÓN', 'ENTREVISTA', 'OFERTA', 'CONTRATADO', 'DESCARTADO']) afirmar(cuerpo.includes(col), 'falta la columna ' + col);
    afirmar(await tarjeta(pagina, 'Marco Díaz').isVisible(), 'no aparece la tarjeta de Marco');
  });

  await paso('E7 mover una tarjeta de Postulado a En revisión desde su diálogo', pagina, async () => {
    await tarjeta(pagina, 'Marco Díaz').click();
    const d = dialogoVisible(pagina);
    await d.locator('select').selectOption({ label: 'En revisión' });
    await d.getByRole('button', { name: 'Mover', exact: true }).click();
    await pagina.waitForTimeout(2500);
    afirmar((await pagina.locator('[role="dialog"]:visible').count()) === 0, 'el diálogo sigue abierto');
    await pagina.reload({ waitUntil: 'load' });
    afirmar((await pagina.getByRole('button', { name: /^Marco Díaz, En revisión/ }).count()) === 1, 'la tarjeta no quedó en En revisión');
  });

  await paso('E8 selección: vista de ranking por coincidencia', pagina, async () => {
    await pagina.goto(BASE + '/seleccion/7?vista=ranking', { waitUntil: 'load' });
    await pagina.getByText(/Ordenado por coincidencia/).first().waitFor({ timeout: 8000 });
    afirmar((await pagina.locator('[wire\\:key^="ranking-"]').count()) >= 1, 'el ranking no tiene filas');
  });

  await paso('E9 matching: «Recalcular» y límite de frecuencia tras 5 pedidos', pagina, async () => {
    await pagina.goto(BASE + '/seleccion/7?vista=ranking', { waitUntil: 'load' });
    const href = await pagina.locator('a[href*="/matching/"]').first().getAttribute('href');
    await pagina.goto(new URL(href, BASE).href, { waitUntil: 'load' });
    await pagina.getByText('Evidencia por requisito').first().waitFor({ timeout: 10000 });
    const boton = pagina.getByRole('button', { name: /Recalcular/ }).first();
    for (let i = 0; i < 6; i++) { await boton.click(); await pagina.waitForTimeout(1200); }
    await pagina.getByText(/Ya pediste varios recálculos seguidos/).first().waitFor({ timeout: 6000 });
  });

  await paso('E10 programar una entrevista para Marco desde su tarjeta', pagina, async () => {
    await pagina.goto(BASE + '/seleccion/7', { waitUntil: 'load' });
    await tarjeta(pagina, 'Marco Díaz').click();
    await pagina.getByRole('link', { name: 'Programar entrevista' }).first().click();
    await pagina.waitForURL(/\/entrevistas/, { timeout: 8000 });
    const d = pagina.locator('[role="dialog"]:visible').first();
    await d.locator('#entrevista-inicio').waitFor({ timeout: 8000 });
    await d.locator('#entrevista-entrevistador').selectOption({ index: 1 });
    await d.locator('#entrevista-inicio').fill('2030-01-15T10:00');
    await d.locator('#entrevista-enlace').fill('https://meet.example.com/abc');
    await d.locator('#entrevista-notas').fill('NOTA INTERNA: preguntar por su último empleo');
    await d.getByRole('button', { name: 'Programar', exact: true }).click();
    await pagina.waitForTimeout(3000);
    afirmar((await pagina.locator('[role="dialog"]:visible').count()) === 0, 'el diálogo de programar sigue abierto (¿error de validación?)');
    afirmar((await pagina.locator('body').innerText()).includes('Marco Díaz'), 'la entrevista no aparece en la lista');
  });

  await paso('E11 entrevistas: «Agregar al calendario» descarga un .ics válido', pagina, async () => {
    await pagina.goto(BASE + '/entrevistas', { waitUntil: 'load' });
    await pagina.getByRole('table').getByRole('button', { name: 'Marco Díaz' }).first().click();
    await pagina.getByText('Entrevista · Marco Díaz').first().waitFor({ timeout: 8000 });
    const enlace = pagina.getByRole('link', { name: 'Agregar al calendario' }).first();
    await enlace.waitFor({ timeout: 8000 });
    const [descarga] = await Promise.all([pagina.waitForEvent('download', { timeout: 10000 }), enlace.click()]);
    const ruta = path.join(SALIDA, 'equipo_' + descarga.suggestedFilename());
    await descarga.saveAs(ruta);
    const ics = fs.readFileSync(ruta, 'utf-8');
    afirmar(ics.startsWith('BEGIN:VCALENDAR') && ics.includes('DTSTART:20300115T150000Z'), 'el .ics no es válido');
  });

  await paso('E12 acceso cruzado: la vacante de otra empresa da 404 en el navegador', pagina, async () => {
    const r = await pagina.goto(BASE + '/vacantes/9', { waitUntil: 'load' });
    afirmar(r.status() === 404, 'estado ' + r.status());
    afirmar(!(await pagina.locator('body').innerText()).includes('Product Designer'), 'se filtró el título de la vacante de otra empresa');
  });

  await paso('E13 el Kanban y la ruta anterior de una vacante ajena también se niegan', pagina, async () => {
    for (const u of ['/seleccion/9', '/vacantes/9/kanban']) {
      const r = await pagina.goto(BASE + u, { waitUntil: 'load' });
      afirmar([403, 404].includes(r.status()), `${u} respondió ${r.status()}`);
    }
  });

  await paso('E14 sin errores de consola ni de servidor en todo el recorrido de empresa', pagina, async () => {
    const graves = errores.filter((e) => !e.includes('status of 404') && !e.includes('status of 403'));
    afirmar(graves.length === 0, JSON.stringify(graves.slice(0, 4)));
  });
  await navegador.close();

  // ───────────── RESPONSIVO ─────────────
  console.log('\n== Responsivo (390 px) ==');
  ({ navegador, pagina, errores } = await abrir({ ancho: 390, alto: 800 }));
  await iniciarSesion(pagina, 'marta@nimbus.com');
  for (const u of ['/dashboard', '/seleccion/7', '/entrevistas', '/vacantes', '/candidatos', '/evaluaciones']) {
    await paso(`R ${u} sin desborde horizontal en móvil`, pagina, () => sinDesborde(pagina, u));
  }
  await navegador.close();

  // ───────────── POSTULANTE ─────────────
  console.log('\n== Postulante: Marco Díaz ==');
  ({ navegador, pagina, errores } = await abrir({ ancho: 1280, alto: 900 }));
  await iniciarSesion(pagina, 'marco@correo.com');

  await paso('P1 mis postulaciones carga', pagina, async () => {
    await pagina.goto(BASE + '/postulante/postulaciones', { waitUntil: 'load' });
    await pagina.locator('tr.tc-row').first().waitFor({ timeout: 8000 });
  });

  await paso('P2 la entrevista aparece con «Agregar al calendario»; el .ics no lleva notas ni entrevistador', pagina, async () => {
    await pagina.goto(BASE + '/postulante/postulaciones', { waitUntil: 'load' });
    await pagina.getByText('Próximas entrevistas').first().waitFor({ timeout: 8000 });
    const enlace = pagina.getByRole('link', { name: 'Agregar al calendario' }).first();
    await enlace.waitFor({ timeout: 5000 });
    const [descarga] = await Promise.all([pagina.waitForEvent('download', { timeout: 10000 }), enlace.click()]);
    const ruta = path.join(SALIDA, 'postulante_' + descarga.suggestedFilename());
    await descarga.saveAs(ruta);
    const ics = fs.readFileSync(ruta, 'utf-8');
    afirmar(ics.includes('Nimbus Corp') && ics.includes('meet.example.com'), 'faltan vacante/empresa/enlace en el .ics');
    afirmar(!/NOTA INTERNA|Notas:|Entrevistador/.test(ics), 'el .ics del postulante filtra información interna');
  });

  await paso('P3 vacantes publicadas', pagina, async () => {
    await pagina.goto(BASE + '/postulante/vacantes', { waitUntil: 'load' });
    afirmar((await pagina.locator('body').innerText()).length > 200, 'página vacía');
  });

  await paso('P4 retirar una postulación (dos pasos: marca de retiro y luego Descartado)', pagina, async () => {
    await pagina.goto(BASE + '/postulante/postulaciones', { waitUntil: 'load' });
    await pagina.locator('tr.tc-row button').first().click();
    await pagina.getByRole('button', { name: 'Retirar postulación' }).first().click();
    await pagina.getByText('Retirar tu postulación a').first().waitFor({ timeout: 8000 }); // el diálogo de confirmación
    await pagina.locator('[role="dialog"]:visible').filter({ hasText: 'Retirar tu postulación a' }).getByRole('button', { name: 'Retirar postulación' }).click();
    await pagina.getByText('Estado: Retirada').first().waitFor({ timeout: 8000 });
  });

  await paso('P5 sin errores de consola ni de servidor en el recorrido del postulante', pagina, async () => {
    afirmar(errores.length === 0, JSON.stringify(errores.slice(0, 4)));
  });
  await navegador.close();

  // ───────────── ADMIN ─────────────
  console.log('\n== Admin SaaS ==');
  ({ navegador, pagina, errores } = await abrir({ ancho: 1440, alto: 900 }));
  await iniciarSesion(pagina, 'admin@talentcloud.ai');
  await paso('A1 panel de la plataforma y avisos con el nombre de la empresa', pagina, async () => {
    await pagina.goto(BASE + '/dashboard', { waitUntil: 'load' });
    await pagina.getByRole('heading', { name: 'Panel de la plataforma' }).waitFor({ timeout: 8000 });
    const t = await pagina.locator('body').innerText();
    afirmar(/ · (Nimbus Corp|Vela Studio|Brío Retail|Ruta Sur|Nuna Salud|Altura Café)/.test(t), 'los avisos no nombran la empresa');
  });
  await paso('A2 empresas, calidad de la IA y reclamaciones cargan', pagina, async () => {
    for (const u of ['/empresas', '/calidad-ia', '/reclamaciones']) {
      const r = await pagina.goto(BASE + u, { waitUntil: 'load' });
      afirmar(r.status() === 200, `${u} → ${r.status()}`);
    }
  });
  await paso('A3 el admin no entra al panel de postulante (403)', pagina, async () => {
    const r = await pagina.goto(BASE + '/postulante/postulaciones', { waitUntil: 'load' });
    afirmar(r.status() === 403, 'estado ' + r.status());
  });
  await paso('A4 sin errores de consola en el recorrido del admin', pagina, async () => {
    const graves = errores.filter((e) => !e.includes('status of 403'));
    afirmar(graves.length === 0, JSON.stringify(graves.slice(0, 4)));
  });
  await navegador.close();

  // ───────────── SEGURIDAD EN NAVEGADOR ─────────────
  console.log('\n== Seguridad en el navegador ==');
  ({ navegador, pagina, errores } = await abrir());
  await paso('S1 la aplicación no se puede incrustar en otro sitio (iframe bloqueado)', pagina, async () => {
    await pagina.goto('about:blank');
    await pagina.setContent(`<iframe id="x" src="${BASE}/login" width="600" height="400"></iframe>`);
    await pagina.waitForTimeout(2500);
    const marcos = pagina.frames().filter((f) => f !== pagina.mainFrame());
    afirmar(marcos.length === 1, 'no se creó el marco');
    const url = marcos[0].url();
    const texto = await marcos[0].evaluate(() => (document.body ? document.body.innerText : '')).catch(() => '');
    afirmar(url.startsWith('chrome-error') || !texto.includes('Iniciar sesión'), 'el login se mostró dentro de un iframe: ' + url);
  });
  await navegador.close();

  // ───────────── RESUMEN ─────────────
  const ok = resultados.filter((r) => r.ok).length;
  console.log(`\nRESUMEN: ${ok}/${resultados.length} pasos correctos`);
  for (const r of resultados.filter((x) => !x.ok)) console.log(' - FALLA:', r.nombre, '→', r.error);
  fs.writeFileSync(path.join(SALIDA, 'resultados.json'), JSON.stringify(resultados, null, 2));
  process.exit(ok === resultados.length ? 0 : 2);
})().catch((e) => { console.error('ERROR FATAL', e); process.exit(1); });

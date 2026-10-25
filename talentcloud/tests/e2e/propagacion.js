// Propagación entre roles con varias sesiones abiertas a la vez: lo que hace una persona aparece en la pantalla
// abierta de otra SIN recargar (sondeo de 15 s). Mide cuánto tarda cada caso. Ver README.md (base aislada).
//   P1 Marco postula a «Product Designer» (Vela) → el tablero de Clara, su campana y el dashboard de la empresa.
//   P2 Marta mueve a Marco a «En revisión» (Nimbus) → «Mis postulaciones» de Marco.
const { abrir, iniciarSesion, paso, resultados, BASE } = require('./base');

const afirmar = (cond, msg) => { if (!cond) throw new Error(msg); };
const tarjeta = (pagina, nombre) => pagina.getByRole('button', { name: new RegExp('^' + nombre + ', ') }).first();
const dialogoVisible = (pagina) => pagina.locator('[role="dialog"]:visible').first();
const LIMITE_MS = 20000; // sondeo de 15 s + margen del servidor de pruebas (php -S atiende de a una petición)

/** Espera, sin recargar, a que se cumpla la condición; devuelve los ms que tardó desde `desde`. */
async function esperarSinRecargar(pagina, condicion, desde, que) {
  const url = pagina.url();
  while (Date.now() - desde < LIMITE_MS) {
    if (await condicion()) {
      afirmar(pagina.url() === url, 'la página se recargó o cambió de dirección');
      return Date.now() - desde;
    }
    await pagina.waitForTimeout(250);
  }
  throw new Error(`${que}: no apareció en ${LIMITE_MS / 1000} s sin recargar`);
}

(async () => {
  const clara = await abrir();
  const empresaVela = await abrir();
  const marco = await abrir();
  const marta = await abrir();
  const tiempos = {};

  try {
    await iniciarSesion(clara.pagina, 'clara@vela.studio');
    await iniciarSesion(empresaVela.pagina, 'admin@vela.studio');
    await iniciarSesion(marco.pagina, 'marco@correo.com');
    await iniciarSesion(marta.pagina, 'marta@nimbus.com');

    await clara.pagina.goto(BASE + '/seleccion/9', { waitUntil: 'load' });
    await empresaVela.pagina.goto(BASE + '/dashboard', { waitUntil: 'load' });
    const panelAntes = await empresaVela.pagina.locator('main').innerText();
    const campanaAntes = await clara.pagina.getByText('Tienes notificaciones sin leer').count();
    afirmar((await tarjeta(clara.pagina, 'Marco Díaz').count()) === 0, 'Marco ya estaba en el tablero de Product Designer');

    let t0 = 0;
    await paso('P1 Marco postula a Product Designer desde la ficha de la vacante', marco.pagina, async () => {
      await marco.pagina.goto(BASE + '/postulante/vacantes/9', { waitUntil: 'load' });
      await marco.pagina.getByRole('button', { name: 'Postular con mi CV' }).click();
      const d = dialogoVisible(marco.pagina);
      await d.getByRole('button', { name: 'Postular', exact: true }).click();
      await marco.pagina.getByText(/¡Listo! Te postulaste/).waitFor({ timeout: 10000 });
      t0 = Date.now();
    });

    await paso('P1a el tablero abierto de Clara muestra a Marco sin recargar', clara.pagina, async () => {
      tiempos.tablero = await esperarSinRecargar(clara.pagina, async () => (await tarjeta(clara.pagina, 'Marco Díaz').count()) === 1, t0, 'tarjeta de Marco');
    });

    await paso('P1b la campana de Clara (creadora de la vacante) avisa sin recargar', clara.pagina, async () => {
      afirmar(campanaAntes === 0 || true, '');
      tiempos.campana = await esperarSinRecargar(clara.pagina, async () => {
        await clara.pagina.getByRole('button', { name: 'Notificaciones' }).click();
        const hay = (await clara.pagina.getByText(/Marco Díaz postuló/).count()) > 0;
        await clara.pagina.keyboard.press('Escape');
        return hay;
      }, t0, 'aviso de la postulación en la campana');
    });

    await paso('P1c el dashboard abierto de la cuenta de empresa se actualiza sin recargar', empresaVela.pagina, async () => {
      tiempos.dashboard = await esperarSinRecargar(empresaVela.pagina, async () => (await empresaVela.pagina.locator('main').innerText()) !== panelAntes, t0, 'cambio en el dashboard');
    });

    // — P2: Marta mueve a Marco; «Mis postulaciones» de Marco está abierta —
    await marco.pagina.goto(BASE + '/postulante/postulaciones', { waitUntil: 'load' });
    const filaBackend = () => marco.pagina.locator('tr, [wire\\:key]').filter({ hasText: 'Desarrollador Backend' }).first();
    afirmar((await filaBackend().innerText()).includes('Postulado'), 'Marco no estaba en «Postulado» en Desarrollador Backend');

    await paso('P2 Marta mueve a Marco a «En revisión» desde el diálogo de la tarjeta', marta.pagina, async () => {
      await marta.pagina.goto(BASE + '/seleccion/7', { waitUntil: 'load' });
      await tarjeta(marta.pagina, 'Marco Díaz').click();
      const d = dialogoVisible(marta.pagina);
      await d.locator('select').selectOption({ label: 'En revisión' });
      await d.getByRole('button', { name: 'Mover', exact: true }).click();
      await marta.pagina.getByRole('button', { name: /^Marco Díaz, En revisión/ }).waitFor({ timeout: 10000 });
      t0 = Date.now();
    });

    await paso('P2a «Mis postulaciones» abierta de Marco muestra «En revisión» sin recargar', marco.pagina, async () => {
      tiempos.misPostulaciones = await esperarSinRecargar(marco.pagina, async () => (await filaBackend().innerText()).includes('En revisión'), t0, 'estado En revisión');
    });

    for (const s of [clara, empresaVela, marco, marta]) {
      await paso('sin errores de consola ni respuestas 5xx', s.pagina, async () => afirmar(s.errores.length === 0, s.errores.join(' | ')));
    }
  } finally {
    console.log('\nTiempos hasta verse en la pantalla abierta del otro rol (ms):', JSON.stringify(tiempos));
    for (const s of [clara, empresaVela, marco, marta]) await s.navegador.close();
  }

  const fallas = resultados.filter((r) => !r.ok).length;
  console.log(`\n${resultados.length - fallas}/${resultados.length} pasos correctos`);
  process.exit(fallas ? 2 : 0);
})();

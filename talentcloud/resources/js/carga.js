/*
 * Estados de carga de Livewire, sin tocar cada botón ni cada vista:
 *
 *  - El botón (.btn) que lanzó una acción muestra un indicador y no admite otro clic hasta que llega la respuesta
 *    (`data-cargando`, ver «interacción» en components.css). Livewire 3.8 no marca el elemento que lanza la petición
 *    (eso llegó con Livewire 4), así que se recuerda el último clic o envío y se asocia a la petición que le sigue.
 *  - Una barra fina arriba (`data-ocupado` en <html>) aparece si una petición iniciada por la persona tarda más de
 *    250 ms. Los sondeos (`wire:poll`) no la encienden: nadie los pidió y parpadearía cada pocos segundos.
 *
 * Las zonas que se vuelven a pintar (tablas, gráficos, tablero) se atenúan con `wire:loading.class.delay`
 * (ver <x-ui.actualizable>).
 */

const RETRASO_BARRA_MS = 250;
const VENTANA_BOTON_MS = 500; // un clic y su petición ocurren casi a la vez
const VENTANA_PERSONA_MS = 1500; // escribir en un buscador con debounce tarda más en llegar al servidor

document.addEventListener('livewire:init', () => {
    let ultimoBoton = null; // { el, t }
    let ultimaAccion = -Infinity; // nunca: con 0, durante el primer segundo y medio de la página todo parecería una acción de la persona
    let pendientes = 0;
    let temporizador = null;

    const recordarBoton = (el) => {
        ultimoBoton = el && el.matches('.btn') ? { el, t: performance.now() } : null;
        ultimaAccion = performance.now();
    };

    document.addEventListener('click', (e) => recordarBoton(e.target.closest('button, a')), true);
    document.addEventListener('submit', (e) => recordarBoton(e.submitter ?? e.target.querySelector('button[type="submit"]')), true);
    for (const tipo of ['input', 'change', 'keydown']) {
        document.addEventListener(tipo, () => { ultimaAccion = performance.now(); }, true);
    }

    window.Livewire.hook('commit', ({ respond, succeed, fail }) => {
        const ahora = performance.now();
        const iniciadaPorPersona = ahora - ultimaAccion < VENTANA_PERSONA_MS;

        let marcado = null;
        if (ultimoBoton && ahora - ultimoBoton.t < VENTANA_BOTON_MS && ! ultimoBoton.el.hasAttribute('data-cargando')) {
            marcado = ultimoBoton.el;
            marcado.setAttribute('data-cargando', '');
        }

        let cuenta = false;
        if (iniciadaPorPersona) {
            cuenta = true;
            pendientes++;
            if (pendientes === 1) {
                temporizador = setTimeout(() => document.documentElement.setAttribute('data-ocupado', ''), RETRASO_BARRA_MS);
            }
        }

        let terminada = false;
        const terminar = () => {
            if (terminada) {
                return;
            }
            terminada = true;
            marcado?.removeAttribute('data-cargando');

            if (cuenta) {
                pendientes = Math.max(0, pendientes - 1);
                if (pendientes === 0) {
                    clearTimeout(temporizador);
                    document.documentElement.removeAttribute('data-ocupado');
                }
            }
        };

        respond(terminar);
        succeed(terminar);
        fail(terminar);
    });
});

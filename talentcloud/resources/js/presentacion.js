/*
 * Página de presentación (/): carrusel de pantallas y maquetas escaladas.
 */
document.addEventListener('alpine:init', () => {
    const reducirMovimiento = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * Carrusel accesible: avanza solo cada `intervalo` ms salvo con "reducir
     * movimiento"; se detiene mientras el puntero o el foco están dentro y
     * tiene botón de pausa (WCAG 2.2.2).
     */
    window.Alpine.data('carruselPresentacion', (total, intervalo = 7000) => ({
        actual: 0,
        total,
        reproduciendo: ! reducirMovimiento(),
        suspendido: false,
        temporizador: null,

        init() {
            this.programar();
            document.addEventListener('visibilitychange', () => (document.hidden ? this.detener() : this.programar()));
        },

        ir(indice) {
            this.actual = (indice + this.total) % this.total;
            this.programar();
        },

        siguiente() {
            this.ir(this.actual + 1);
        },

        anterior() {
            this.ir(this.actual - 1);
        },

        alternar() {
            this.reproduciendo = ! this.reproduciendo;
            this.programar();
        },

        suspender(valor) {
            this.suspendido = valor;
            this.programar();
        },

        programar() {
            this.detener();

            if (this.reproduciendo && ! this.suspendido) {
                this.temporizador = setInterval(() => {
                    this.actual = (this.actual + 1) % this.total;
                }, intervalo);
            }
        },

        detener() {
            clearInterval(this.temporizador);
        },

        destroy() {
            this.detener();
        },
    }));

    /**
     * Maqueta dibujada a un tamaño fijo y escalada al ancho disponible, para
     * que se vea igual en cualquier pantalla. El contenedor reserva el alto con
     * aspect-ratio, así la página no salta antes de que cargue el script.
     */
    window.Alpine.data('maquetaEscalada', (ancho, alto) => ({
        escala: 1,

        init() {
            const medir = () => {
                this.escala = Math.min(1, this.$el.clientWidth / ancho);
            };

            medir();
            this.observador = new ResizeObserver(medir);
            this.observador.observe(this.$el);
        },

        get estiloMaqueta() {
            return `width: ${ancho}px; height: ${alto}px; transform: scale(${this.escala}); transform-origin: top left`;
        },

        destroy() {
            this.observador?.disconnect();
        },
    }));
});

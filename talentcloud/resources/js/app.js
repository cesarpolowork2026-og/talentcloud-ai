import './presentacion';
import './carga';

document.addEventListener('alpine:init', () => {
    /**
     * Columna del Kanban con arrastrar y soltar (SortableJS).
     * Al soltar, el DOM se devuelve a su estado y se pide a Livewire que
     * guarde el movimiento: el servidor es la única fuente de verdad del
     * orden y el tablero se vuelve a pintar con lo guardado.
     */
    window.Alpine.data('columnaKanban', (habilitado) => ({
        async init() {
            if (! habilitado) {
                return;
            }

            // SortableJS (~45 KB) solo se descarga en el tablero editable, no en todas las páginas.
            const { default: Sortable } = await import('sortablejs');

            Sortable.create(this.$el, {
                group: 'kanban',
                animation: 150,
                // Las tarjetas fijas (postulación retirada por el candidato) no se arrastran.
                draggable: '[data-postulacion]:not([data-fija])',
                filter: 'button',
                preventOnFilter: false,
                ghostClass: 'kanban-fantasma',
                onEnd: (evento) => {
                    const id = Number(evento.item.dataset.postulacion);
                    const destino = evento.to.dataset.etapa;
                    const posicion = evento.newDraggableIndex;
                    const mismoLugar = evento.from === evento.to && evento.oldDraggableIndex === evento.newDraggableIndex;

                    evento.from.insertBefore(evento.item, evento.from.children[evento.oldIndex] ?? null);

                    if (! mismoLugar) {
                        this.$wire.mover(id, destino, posicion);
                    }
                },
            });
        },
    }));

    /**
     * Selector de competencias: etiquetas elegidas del catálogo (por nombre o sinónimo),
     * con «¿Quisiste decir…?» ante un error de tipeo y «Crear» para una que no existe.
     * El catálogo viene del servidor; la selección es la lista de nombres de la propiedad
     * de Livewire `modelo`, que el servidor vuelve a resolver contra el catálogo.
     */
    window.Alpine.data('selectorCompetencias', ({ catalogo, modelo, maximo = 20 }) => {
        const limpiar = (texto) => texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
        const bigramas = (texto) => {
            const t = limpiar(texto).replace(/ /g, '');
            const pares = new Map();

            for (let i = 0; i < t.length - 1; i++) {
                const par = t.slice(i, i + 2);
                pares.set(par, (pares.get(par) ?? 0) + 1);
            }

            return pares;
        };
        // Coeficiente de Dice sobre pares de letras: tolera "postgre", "kubernets", "javscript".
        const parecido = (a, b) => {
            const x = bigramas(a);
            const y = bigramas(b);
            let comunes = 0;
            let total = 0;

            x.forEach((n, par) => { comunes += Math.min(n, y.get(par) ?? 0); total += n; });
            y.forEach((n) => { total += n; });

            return total === 0 ? 0 : (2 * comunes) / total;
        };
        const indice = catalogo.map((c) => ({ ...c, claves: [c.nombre, ...(c.sinonimos ?? [])].map(limpiar) }));

        return {
            seleccion: [],
            texto: '',
            abierto: false,
            activa: -1,
            maximo,

            init() {
                this.seleccion = [...(this.$wire.get(modelo) ?? [])];
                // El servidor reinicia la lista al abrir el formulario de nuevo.
                this.$watch(() => this.$wire.get(modelo), (valor) => {
                    if (JSON.stringify(valor ?? []) !== JSON.stringify(this.seleccion)) {
                        this.seleccion = [...(valor ?? [])];
                    }
                });
            },

            sincronizar() {
                this.$wire.$set(modelo, [...this.seleccion], false);
            },

            get elegidas() {
                return new Set(this.seleccion.map(limpiar));
            },

            enCatalogo(nombre) {
                const clave = limpiar(nombre);

                return indice.find((c) => c.claves.includes(clave)) ?? null;
            },

            get opciones() {
                const consulta = limpiar(this.texto);

                if (consulta === '') {
                    return [];
                }

                const lista = [];
                const yaElegida = (c) => c.claves.some((k) => this.elegidas.has(k));

                for (const c of indice) {
                    if (yaElegida(c)) {
                        continue;
                    }

                    const empieza = c.claves.some((k) => k.startsWith(consulta));
                    const contiene = c.claves.some((k) => k.includes(consulta));

                    if (empieza || contiene) {
                        const porSinonimo = ! limpiar(c.nombre).includes(consulta);

                        lista.push({
                            tipo: 'catalogo',
                            nombre: c.nombre,
                            detalle: porSinonimo ? 'también «' + (c.sinonimos.find((s) => limpiar(s).includes(consulta)) ?? '') + '»' : c.categoria,
                            orden: empieza ? 0 : 1,
                        });
                    }
                }

                lista.sort((a, b) => a.orden - b.orden || a.nombre.localeCompare(b.nombre, 'es'));

                // Sin coincidencias directas: el nombre del catálogo más parecido.
                if (lista.length === 0 && consulta.length >= 3) {
                    const cercano = indice
                        .filter((c) => ! yaElegida(c))
                        .map((c) => ({ c, puntaje: Math.max(...c.claves.map((k) => parecido(k, consulta))) }))
                        .filter((p) => p.puntaje >= 0.5)
                        .sort((a, b) => b.puntaje - a.puntaje)[0];

                    if (cercano) {
                        lista.push({ tipo: 'sugerida', nombre: cercano.c.nombre, detalle: '¿Quisiste decir esta?', orden: 0 });
                    }
                }

                const exacta = this.enCatalogo(this.texto) !== null || this.elegidas.has(consulta);

                if (! exacta) {
                    lista.push({ tipo: 'nueva', nombre: this.texto.trim().replace(/\s+/g, ' '), detalle: 'Crear competencia nueva', orden: 2 });
                }

                return lista.slice(0, 8);
            },

            get lleno() {
                return this.seleccion.length >= this.maximo;
            },

            esNueva(nombre) {
                return this.enCatalogo(nombre) === null;
            },

            agregar(opcion) {
                if (! opcion || this.lleno || this.elegidas.has(limpiar(opcion.nombre))) {
                    this.texto = '';
                    return;
                }

                this.seleccion.push(opcion.nombre);
                this.sincronizar();
                this.texto = '';
                this.activa = -1;
                this.abierto = false;
            },

            quitar(indiceDeLaEtiqueta) {
                this.seleccion.splice(indiceDeLaEtiqueta, 1);
                this.sincronizar();
                this.$refs.campo.focus();
            },

            // Enter o coma: la opción resaltada o, si no hay, la mejor coincidencia.
            confirmar() {
                const lista = this.opciones;

                if (lista.length === 0) {
                    return;
                }

                this.agregar(lista[this.activa >= 0 ? this.activa : 0]);
            },

            mover(paso) {
                const total = this.opciones.length;

                if (total === 0) {
                    return;
                }

                this.abierto = true;
                this.activa = (this.activa + paso + total) % total;
            },

            retroceso() {
                if (this.texto === '' && this.seleccion.length > 0) {
                    this.seleccion.pop();
                    this.sincronizar();
                }
            },
        };
    });

    /**
     * Buscador con resultados del servidor: para elegir una vacante o una postulación entre miles sin
     * enviar todas al navegador. Cada vez que se escribe (con una pausa) llama a un método del
     * componente de Livewire que devuelve hasta 20 coincidencias [{ id, etiqueta, detalle }].
     * `modelo`: propiedad de Livewire que guarda el id elegido (opcional). Al elegir lanza el
     * evento `elegido` con { id, etiqueta }; con `vacio` se puede volver a «ninguna».
     */
    window.Alpine.data('buscadorRemoto', ({ metodo, modelo = null, etiqueta = '', vacio = null }) => ({
        texto: etiqueta,
        elegida: etiqueta,
        opciones: [],
        abierto: false,
        activa: -1,
        cargando: false,
        pedido: 0,
        pausa: null,

        init() {
            // El servidor puede vaciar la elección (por ejemplo, al reiniciar un formulario).
            if (modelo) {
                this.$watch(() => this.$wire.get(modelo), (valor) => {
                    if ((valor === '' || valor === null) && this.elegida !== '') {
                        this.elegida = '';
                        this.texto = '';
                    }
                });
            }
        },

        escribir() {
            this.abierto = true;
            this.activa = -1;
            clearTimeout(this.pausa);
            this.pausa = setTimeout(() => this.buscar(), 250);
        },

        abrir() {
            this.abierto = true;
            if (this.opciones.length === 0) {
                this.buscar();
            }
        },

        async buscar() {
            const numero = ++this.pedido;
            this.cargando = true;

            // Con la etiqueta ya elegida en el campo se muestran las sugerencias iniciales, no un filtro por ella.
            const consulta = this.texto === this.elegida ? '' : this.texto;
            const resultado = await this.$wire.call(metodo, consulta);

            if (numero === this.pedido) {
                this.opciones = vacio !== null && consulta === '' ? [{ id: '', etiqueta: vacio, detalle: null }, ...resultado] : resultado;
                this.cargando = false;
            }
        },

        elegir(opcion) {
            if (! opcion) {
                return;
            }

            this.elegida = opcion.id === '' ? '' : opcion.etiqueta;
            this.texto = this.elegida;
            this.abierto = false;

            if (modelo) {
                this.$wire.$set(modelo, String(opcion.id));
            }

            this.$dispatch('elegido', { id: opcion.id, etiqueta: opcion.etiqueta });
        },

        mover(paso) {
            const total = this.opciones.length;

            if (total === 0) {
                return;
            }

            this.abierto = true;
            this.activa = (this.activa + paso + total) % total;
        },

        confirmar() {
            this.elegir(this.opciones[this.activa >= 0 ? this.activa : 0]);
        },
    }));
});

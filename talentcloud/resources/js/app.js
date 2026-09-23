import './bootstrap';
import Sortable from 'sortablejs';

document.addEventListener('alpine:init', () => {
    /**
     * Columna del Kanban con arrastrar y soltar (SortableJS).
     * Al soltar, el DOM se devuelve a su estado y se pide a Livewire que
     * guarde el movimiento: el servidor es la única fuente de verdad del
     * orden y el tablero se vuelve a pintar con lo guardado.
     */
    window.Alpine.data('columnaKanban', (habilitado) => ({
        init() {
            if (! habilitado) {
                return;
            }

            Sortable.create(this.$el, {
                group: 'kanban',
                animation: 150,
                draggable: '[data-postulacion]',
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
});

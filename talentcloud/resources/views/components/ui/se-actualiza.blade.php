{{--
    Sondeo de una pantalla con `use SeActualizaSola`: cada 15 s pregunta si cambió lo que muestra y solo entonces
    vuelve a pintar. Va en un elemento propio para convivir con otros sondeos de la vista (CV en análisis, pago en
    curso). Sin `.visible` (un elemento oculto nunca lo estaría): Livewire ya espacia el sondeo en pestañas de fondo.
--}}
<div wire:poll.15s="revisarCambios" hidden aria-hidden="true"></div>

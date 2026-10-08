{{--
    Texto principal de una fila o tarjeta clicable (.tc-row): el <button> que la abre con el
    teclado y con lectores de pantalla (WCAG 2.1.1). La fila también se abre con un clic en
    cualquier parte; usar wire:click.stop aquí para no abrirla dos veces.
--}}
<button type="button" {{ $attributes->class('text-left hover:underline') }}>{{ $slot }}</button>

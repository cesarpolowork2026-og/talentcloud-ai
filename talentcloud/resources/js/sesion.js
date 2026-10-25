/*
 * Sesión vencida (419) en una petición de Livewire: en vez del aviso «This page has expired» (o la pantalla de error),
 * se vuelve a cargar la página. Si la sesión sigue viva, la persona continúa donde estaba con un token nuevo; si no,
 * el servidor la lleva al inicio de sesión. Pasa, por ejemplo, al entrar con otra cuenta en otra pestaña del mismo
 * navegador (comparten la sesión) o tras dejar la página abierta más de lo que dura la sesión.
 */
document.addEventListener('livewire:init', () => {
    let recargando = false;

    window.Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status !== 419) {
                return;
            }

            preventDefault();

            if (! recargando) {
                recargando = true;
                window.location.reload();
            }
        });
    });
});

<?php

use App\Livewire\Actions\Logout;
use App\Models\User;
use App\Support\Navegacion;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component
{
    /** Ruta de la página que contiene la barra (en las peticiones de Livewire request() es otra). */
    #[Locked]
    public string $rutaActual = '';

    public function mount(): void
    {
        $this->rutaActual = request()->route()?->getName() ?? '';
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect(route('login'), navigate: true);
    }

    public function marcarComoLeidas(): void
    {
        $this->usuario()->unreadNotifications()->update(['read_at' => now()]);
    }

    /** Abre una notificación: la marca como leída y lleva a su pantalla, si tiene. */
    public function abrirNotificacion(string $id): void
    {
        $notificacion = $this->usuario()->notifications()->find($id);
        abort_if($notificacion === null, 404);

        $notificacion->markAsRead();

        if (filled($notificacion->data['url'] ?? null)) {
            $this->redirect($notificacion->data['url'], navigate: true);
        }
    }

    private function usuario(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        /** @var User $user */
        $user = auth()->user();

        $enlaces = array_map(fn (array $enlace) => [
            ...$enlace,
            'href' => Route::has($enlace['ruta']) ? route($enlace['ruta']) : null,
            'activo' => Str::is($enlace['patron'], $this->rutaActual),
        ], Navegacion::enlaces($user));

        return [
            'enlaces' => $enlaces,
            'inicial' => Navegacion::inicial($user),
            'etiquetaPerfil' => Navegacion::etiquetaPerfil($user),
            'notificaciones' => $user->notifications()->latest()->limit(5)->get(),
            'hayNoLeidas' => $user->unreadNotifications()->exists(),
        ];
    }
}; ?>

{{-- Barra de navegación del prototipo (TalentCloud App.dc.html). --}}
<nav
    class="nav barra relative flex-wrap"
    x-data="{ notificaciones: false, perfil: false, menu: false }"
    x-on:keydown.escape.window="notificaciones = false; perfil = false; menu = false"
>
    <a href="{{ Navegacion::rutaInicio(auth()->user()) }}" wire:navigate class="nav-brand hover:!no-underline" aria-label="TalentCloud AI, inicio">
        <x-marca />
    </a>

    {{-- Enlaces (escritorio) --}}
    <div class="hidden items-center gap-4 lg:flex">
        @foreach ($enlaces as $enlace)
            @if ($enlace['href'])
                <a href="{{ $enlace['href'] }}" wire:navigate class="barra-enlace" @if ($enlace['activo']) aria-current="page" @endif>{{ $enlace['etiqueta'] }}</a>
            @else
                <a aria-disabled="true" tabindex="-1" title="Disponible próximamente" class="cursor-default opacity-[0.45]">{{ $enlace['etiqueta'] }}</a>
            @endif
        @endforeach
    </div>

    <div class="relative ml-auto flex items-center gap-3">
        {{-- Fondo que cierra los menús al hacer clic fuera --}}
        <div x-show="notificaciones || perfil" x-cloak x-on:click="notificaciones = false; perfil = false" class="fixed inset-0 z-[15]"></div>

        {{-- Notificaciones --}}
        <div class="relative">
            <button
                type="button"
                aria-label="Notificaciones"
                x-bind:aria-expanded="notificaciones"
                x-on:click="notificaciones = ! notificaciones; perfil = false"
                class="barra-control relative flex rounded p-1"
            >
                <x-ui.icon name="bell" :size="20" />
                @if ($hayNoLeidas)
                    <span class="absolute right-0.5 top-0.5 h-2.5 w-2.5 rounded-full bg-accent-2 ring-2 ring-accent-800" aria-hidden="true"></span>
                    <span class="sr-only">Tienes notificaciones sin leer</span>
                @endif
            </button>

            <div x-show="notificaciones" x-cloak x-transition.opacity.duration.100ms class="card elev-lg absolute right-0 top-[40px] z-20 w-[280px] p-3">
                <div class="mb-2 flex items-baseline justify-between gap-2">
                    <div class="card-kicker">Notificaciones</div>
                    @if ($hayNoLeidas)
                        <button type="button" wire:click="marcarComoLeidas" class="whitespace-nowrap text-12 text-accent-700 hover:underline">Marcar como leídas</button>
                    @endif
                </div>
                @forelse ($notificaciones as $notificacion)
                    <button
                        type="button"
                        wire:key="notificacion-{{ $notificacion->id }}"
                        wire:click="abrirNotificacion('{{ $notificacion->id }}')"
                        @class([
                            'block w-full border-t border-divider py-2 text-left text-13 hover:text-accent-700',
                            'font-semibold text-ink' => $notificacion->unread(),
                            'text-neutral-800' => $notificacion->read(),
                        ])
                    >
                        {{ $notificacion->data['texto'] ?? '' }}
                        @if ($notificacion->unread())
                            <span class="sr-only">(sin leer)</span>
                        @endif
                    </button>
                @empty
                    <div class="border-t border-divider py-2 text-13 text-neutral-600">No tienes notificaciones.</div>
                @endforelse
            </div>
        </div>

        {{-- Perfil --}}
        <div class="relative">
            <button
                type="button"
                x-bind:aria-expanded="perfil"
                x-on:click="perfil = ! perfil; notificaciones = false"
                class="barra-control flex items-center gap-2 text-13"
            >
                <span class="flex h-[26px] w-[26px] items-center justify-center rounded-full bg-accent-100 text-12 font-semibold text-accent-700" aria-hidden="true">{{ $inicial }}</span>
                <span class="hidden sm:inline">{{ $etiquetaPerfil }}</span>
                <span class="sr-only sm:hidden">Menú de perfil</span>
            </button>

            <div x-show="perfil" x-cloak x-transition.opacity.duration.100ms class="card elev-lg absolute right-0 top-[40px] z-20 w-[170px] p-2">
                <a href="{{ route('profile') }}" wire:navigate class="block p-2 text-14">Mi perfil</a>
                <button type="button" wire:click="logout" class="block w-full p-2 text-left text-14 text-accent-700 hover:text-accent-800 hover:underline">Cerrar sesión</button>
            </div>
        </div>

        {{-- Menú compacto (móvil y tableta) --}}
        <button
            type="button"
            class="btn btn-ghost btn-icon lg:hidden"
            aria-label="Abrir menú"
            x-bind:aria-expanded="menu"
            x-on:click="menu = ! menu"
        >
            <x-ui.icon name="list" :size="20" x-show="! menu" />
            <x-ui.icon name="x" :size="20" x-show="menu" x-cloak />
        </button>
    </div>

    <div x-show="menu" x-cloak class="flex w-full flex-col gap-1 pt-2 lg:hidden">
        @foreach ($enlaces as $enlace)
            @if ($enlace['href'])
                <a href="{{ $enlace['href'] }}" wire:navigate class="barra-enlace py-2" @if ($enlace['activo']) aria-current="page" @endif>{{ $enlace['etiqueta'] }}</a>
            @else
                <a aria-disabled="true" tabindex="-1" class="cursor-default py-2 opacity-[0.45]">{{ $enlace['etiqueta'] }}</a>
            @endif
        @endforeach
    </div>
</nav>

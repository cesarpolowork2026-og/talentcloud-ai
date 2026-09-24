<?php

use App\Models\Empresa;
use App\Models\User;
use App\Notifications\AvisoGeneral;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->marta = User::factory()->deEmpresa(Empresa::factory()->create())->reclutador()->create();
    actuarComo($this->marta);
});

function avisoConEnlace(User $usuario, string $texto, ?string $url = null): DatabaseNotification
{
    $usuario->notify(new AvisoGeneral($texto));
    $notificacion = $usuario->notifications()->latest()->firstOrFail();

    if ($url !== null) {
        $notificacion->update(['data' => ['texto' => $texto, 'url' => $url]]);
    }

    return $notificacion;
}

it('muestra las notificaciones y señala las no leídas', function () {
    avisoConEnlace($this->marta, 'Entrevista de Iván Torres pendiente de confirmar.');

    Volt::test('layout.navigation')
        ->assertSee('Entrevista de Iván Torres pendiente de confirmar.')
        ->assertSee('Tienes notificaciones sin leer')
        ->assertSee('Marcar como leídas');
});

it('marca todas como leídas', function () {
    avisoConEnlace($this->marta, 'Uno.');
    avisoConEnlace($this->marta, 'Dos.');

    Volt::test('layout.navigation')
        ->call('marcarComoLeidas')
        ->assertDontSee('Tienes notificaciones sin leer')
        ->assertDontSee('Marcar como leídas');

    expect($this->marta->unreadNotifications()->count())->toBe(0);
});

it('abre una notificación: la marca como leída y lleva a su pantalla', function () {
    $notificacion = avisoConEnlace($this->marta, 'Entrevista con Sofía Nieto.', route('entrevistas.index'));

    Volt::test('layout.navigation')
        ->call('abrirNotificacion', $notificacion->id)
        ->assertRedirect(route('entrevistas.index'));

    expect($notificacion->fresh()->read_at)->not->toBeNull();
});

it('no abre notificaciones de otra persona', function () {
    $ajena = avisoConEnlace(User::factory()->create(), 'Privada.');

    Volt::test('layout.navigation')->call('abrirNotificacion', $ajena->id)->assertNotFound();
    expect($ajena->fresh()->read_at)->toBeNull();
});

<?php

/*
 * Lo que se dice del pago es lo mismo en /planes y en /terminos: sigue a CobroEnLinea::habilitado().
 */

it('sin pasarela, /planes y /terminos dicen que el equipo coordina el pago', function () {
    config(['talentcloud.pagos.pasarela' => null]);

    $this->get('/planes')->assertOk()
        ->assertSee('El equipo de TalentCloud te escribe para coordinar el pago y lo aplica.')
        ->assertDontSee('Stripe');

    $this->get('/terminos')->assertOk()->assertDontSee('a través de Stripe');
});

it('con pasarela, /planes y /terminos dicen que se paga con tarjeta en Stripe', function () {
    config(['talentcloud.pagos.pasarela' => 'stripe']);

    $this->get('/planes')->assertOk()
        ->assertSee('se pagan con tarjeta en la página segura de Stripe')
        ->assertDontSee('coordinar el pago y lo aplica');

    $this->get('/terminos')->assertOk()->assertSee('a través de Stripe');
});

it('los botones de la tabla de planes no prometen una prueba de Starter', function () {
    $this->get('/planes')->assertOk()
        ->assertDontSee('Probar Starter gratis')
        ->assertDontSee('Probar Pro gratis')
        ->assertSee('Crear empresa gratis');
});

<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;

beforeEach(function () {
    config(['mail.default' => 'array']);
});

function correosEnviadosDeVerdad(): Collection
{
    return app('mailer')->getSymfonyTransport()->messages();
}

it('no envía correo a los dominios de la demostración', function () {
    Mail::raw('Hola', fn ($m) => $m->to('marco@correo.com')->subject('Prueba'));

    expect(correosEnviadosDeVerdad())->toHaveCount(0);
});

it('envía a las direcciones reales y quita solo las de la demostración', function () {
    Mail::raw('Hola', fn ($m) => $m->to(['persona@gmail.com', 'admin@nimbus.com'])->cc('otra@NIMBUS.com')->subject('Prueba'));

    $enviados = correosEnviadosDeVerdad();
    expect($enviados)->toHaveCount(1);

    $original = $enviados->first()->getOriginalMessage();
    expect(array_map(fn (Address $a) => $a->getAddress(), $original->getTo()))->toBe(['persona@gmail.com'])
        ->and($original->getCc())->toBe([]);
});

it('responde a la dirección configurada', function () {
    config(['mail.reply_to' => ['address' => 'soporte@gmail.com', 'name' => 'TalentCloud AI']]);
    app()->forgetInstance('mail.manager');
    app()->forgetInstance('mailer');
    Mail::clearResolvedInstances();

    Mail::raw('Hola', fn ($m) => $m->to('persona@gmail.com')->subject('Prueba'));

    $original = correosEnviadosDeVerdad()->first()->getOriginalMessage();
    expect($original->getReplyTo()[0]->getAddress())->toBe('soporte@gmail.com');
});

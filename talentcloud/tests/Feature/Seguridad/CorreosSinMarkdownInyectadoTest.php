<?php

use App\DTO\DatosDeEntrevista;
use App\Models\Empresa;
use App\Models\Invitacion;
use App\Notifications\AvisoCambioDePlan;
use App\Notifications\AvisoCorreoCambiado;
use App\Notifications\AvisoDeEntrevista;
use App\Notifications\AvisoEntrevistaReprogramada;
use App\Notifications\AvisoEstadoDePostulacion;
use App\Notifications\AvisoNuevaPostulacion;
use App\Notifications\AvisoPagoFallido;
use App\Notifications\AvisoPostulacionRetirada;
use App\Notifications\AvisoSolicitudDePlan;
use App\Notifications\AvisoVencimientoDePlan;
use App\Notifications\InvitacionRecibida;
use App\Support\TextoDeCorreo;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

/*
 * Las líneas de un correo se interpretan como Markdown: un título de vacante, un nombre de empresa o de persona
 * como «[Verifica tu cuenta](https://sitio-malo.example)» llegaría como un enlace camuflado, en un correo que sale de
 * TalentCloud hacia direcciones que el atacante elige. Todo texto escrito por una persona va como literal.
 */

const ENLACE_MALO = 'https://sitio-malo.example/entra';
const TEXTO_HOSTIL = '[Verifica tu cuenta aquí]('.ENLACE_MALO.') y ![](https://sitio-malo.example/pixel.png)';

/** Crea la notificación saltándose su constructor privado (las pruebas solo necesitan las propiedades). */
function notificacionCon(string $clase, array $propiedades): Notification
{
    $reflexion = new ReflectionClass($clase);
    $objeto = $reflexion->newInstanceWithoutConstructor();

    foreach ($propiedades as $nombre => $valor) {
        $reflexion->getProperty($nombre)->setValue($objeto, $valor);
    }

    return $objeto;
}

function correoDe(Notification $aviso, object $destinatario = new AnonymousNotifiable): string
{
    return $aviso->toMail($destinatario)->render()->toHtml();
}

function sinEnlaceMalo(string $html): void
{
    expect($html)->not->toContain('href="'.ENLACE_MALO)
        ->and($html)->not->toContain('src="https://sitio-malo.example')
        ->and($html)->not->toContain('<img src="https://sitio-malo');
}

it('el helper deja el texto literal y no toca el texto corriente', function () {
    expect(TextoDeCorreo::literal('Tu postulación a Analista (QA) está en revisión.'))->toBe('Tu postulación a Analista \(QA\) está en revisión\.')
        ->and(TextoDeCorreo::literal('Ñandú & café <b>'))->toBe('Ñandú & café <b>');
});

it('no convierte en enlace el texto de una invitación, hacia una dirección externa', function () {
    $empresa = new Empresa(['nombre' => TEXTO_HOSTIL]);
    $invitacion = new Invitacion(['nombre' => TEXTO_HOSTIL, 'rol' => 'reclutador']);
    $invitacion->expira_at = now()->addDays(3);

    $html = correoDe(new InvitacionRecibida($empresa, $invitacion, 'token-de-prueba', TEXTO_HOSTIL));

    sinEnlaceMalo($html);
    expect($html)->toContain('Aceptar invitación');
});

it('tampoco en los avisos de estado, entrevista, postulación, plan y pago', function (string $clase, array $propiedades) {
    sinEnlaceMalo(correoDe(notificacionCon($clase, $propiedades)));
})->with(function () {
    $datos = new DatosDeEntrevista(
        candidato: TEXTO_HOSTIL, vacante: TEXTO_HOSTIL, empresa: TEXTO_HOSTIL,
        fecha: '14 oct · 10:00', duracionMin: 45, modalidad: 'Videollamada', enlace: null, candidatoId: 1,
    );

    return [
        'estado de la postulación' => [AvisoEstadoDePostulacion::class, ['asunto' => 'Asunto', 'texto' => 'Tu postulación a '.TEXTO_HOSTIL, 'url' => null]],
        'entrevista programada' => [AvisoDeEntrevista::class, ['datos' => $datos, 'cancelada' => false, 'paraCandidato' => true]],
        'entrevista cancelada' => [AvisoDeEntrevista::class, ['datos' => $datos, 'cancelada' => true, 'paraCandidato' => false]],
        'entrevista reprogramada' => [AvisoEntrevistaReprogramada::class, ['datos' => $datos, 'fechaAnterior' => '13 oct', 'paraCandidato' => true]],
        'nueva postulación' => [AvisoNuevaPostulacion::class, ['texto' => TEXTO_HOSTIL, 'url' => 'https://app.example/x', 'candidatoId' => null]],
        'postulación retirada' => [AvisoPostulacionRetirada::class, ['texto' => TEXTO_HOSTIL, 'url' => 'https://app.example/x', 'candidatoId' => null]],
        'cambio de plan' => [AvisoCambioDePlan::class, ['texto' => TEXTO_HOSTIL, 'limites' => TEXTO_HOSTIL, 'url' => 'https://app.example/x']],
        'solicitud de plan' => [AvisoSolicitudDePlan::class, ['texto' => TEXTO_HOSTIL, 'url' => 'https://app.example/x']],
        'pago fallido' => [AvisoPagoFallido::class, ['texto' => TEXTO_HOSTIL, 'url' => 'https://app.example/x']],
        'correo cambiado' => [AvisoCorreoCambiado::class, ['texto' => TEXTO_HOSTIL, 'soporte' => TEXTO_HOSTIL]],
        'plan por vencer' => [AvisoVencimientoDePlan::class, ['asunto' => 'Asunto', 'texto' => TEXTO_HOSTIL, 'consecuencia' => 'Texto fijo', 'url' => 'https://app.example/x']],
    ];
});

it('el botón de la videollamada solo acepta enlaces http o https', function () {
    $datos = new DatosDeEntrevista(
        candidato: 'Marco', vacante: 'Analista', empresa: 'Nimbus',
        fecha: '14 oct · 10:00', duracionMin: 45, modalidad: 'Videollamada', enlace: 'javascript:alert(document.cookie)', candidatoId: 1,
    );

    $html = correoDe(notificacionCon(AvisoDeEntrevista::class, ['datos' => $datos, 'cancelada' => false, 'paraCandidato' => true]));

    expect($html)->not->toContain('javascript:');
});

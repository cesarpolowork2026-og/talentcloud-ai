<?php

namespace App\Http\Controllers;

use App\Pagos\AvisoNoValido;
use App\Pagos\CobroEnLinea;
use App\Pagos\PasarelaDePago;
use App\Services\Pagos\AtenderAvisoDePago;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Avisos (webhooks) de la pasarela de pago: POST /stripe/webhook, sin sesión ni
 * token CSRF; lo que los autentica es la firma (cabecera Stripe-Signature).
 * Un aviso sin firma válida no toca nada (400). Si algo falla al atenderlo
 * (500), la pasarela lo reintenta: atenderlo de nuevo da el mismo resultado.
 */
class AvisoDePagoController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(CobroEnLinea::habilitado(), 404);

        try {
            $aviso = app(PasarelaDePago::class)->leerAviso($request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (AvisoNoValido) {
            return response('Firma no válida.', 400);
        }

        app(AtenderAvisoDePago::class)->ejecutar($aviso);

        return response('Recibido.');
    }
}

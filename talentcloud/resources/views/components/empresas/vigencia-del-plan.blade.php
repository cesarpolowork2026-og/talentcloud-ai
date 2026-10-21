{{--
    Frase sobre el vencimiento de un plan de pago asignado a mano (Empresa::tieneVencimientoPropio()): cuándo vence
    o que ya venció y qué rige ahora. Solo texto, sin envoltorio: quien la usa decide el elemento.
    propia: dirigida a la empresa («Tu plan…»); si no, al admin SaaS que la mira desde fuera.
--}}
@props(['empresa', 'propia' => false])

@php
    $plan = $empresa->plan->etiqueta();
    $fecha = \App\Support\Formato::fechaCorta($empresa->plan_vence_at);
    $dias = $empresa->diasParaVencer();
@endphp

@if ($empresa->planVencido())
    @if ($propia)
        Tu plan {{ $plan }} venció el {{ $fecha }} y ahora rige el plan Gratis: conservas todo lo que tengas, pero no podrás sumar por encima de sus límites. Solicita su renovación para recuperarlo.
    @else
        El plan {{ $plan }} venció el {{ $fecha }}: hoy rige el plan Gratis. Renuévalo con «Cambiar plan».
    @endif
@else
    @php($quedan = $dias === 1 ? 'queda 1 día' : 'quedan '.$dias.' días')
    @if ($propia)
        Tu plan {{ $plan }} vence el {{ $fecha }} ({{ $quedan }}). Después pasarás al plan Gratis: conservas todo lo que tengas, pero no podrás sumar por encima de sus límites.
    @else
        Vence el {{ $fecha }} ({{ $quedan }}); después, Gratis.
    @endif
@endif

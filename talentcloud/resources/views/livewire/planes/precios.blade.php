{{-- Planes y precios (ampliación sobre el prototipo). Precios de PlanEmpresa, en soles y sin IGV. --}}
@php($prueba = \App\Enums\PlanEmpresa::PLAN_DE_PRUEBA)
<div>
    <x-ui.page-header title="Planes y precios" subtitle="Pagas por las vacantes que tienes abiertas, no por usuario: invita a los jefes de área sin costo extra." compact />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded border border-divider bg-surface p-4">
        <p class="m-0 max-w-[70ch] text-15">
            <strong>Empieza gratis con {{ \App\Enums\PlanEmpresa::DIAS_DE_PRUEBA }} días del plan {{ $prueba->etiqueta() }}.</strong>
            Al terminar la prueba tu empresa sigue en el plan Gratis, sin perder nada, y eliges cuándo cambiar.
        </p>
        <x-ui.button variant="primary" :href="route('register')" wire:navigate class="whitespace-nowrap">Crear empresa gratis</x-ui.button>
    </div>

    <h2 class="sr-only">Planes</h2>
    <x-planes.tabla />

    <p class="mt-4 text-13 text-neutral-700">Precios en soles; no incluyen IGV. Todos los planes incluyen todas las funciones: vacantes, Kanban, matching explicable con evidencia, entrevistas, evaluaciones, exportación a Excel y bolsa de empleos. Los postulantes nunca pagan.</p>

    <section class="mt-10 max-w-[75ch]" aria-labelledby="preguntas">
        <h2 id="preguntas" class="text-20">Preguntas frecuentes</h2>
        <dl class="grid gap-4">
            <div>
                <dt class="font-semibold">¿Qué es una vacante activa?</dt>
                <dd class="m-0 text-neutral-800">Una vacante abierta o en revisión. Los borradores y las vacantes cerradas no cuentan.</dd>
            </div>
            <div>
                <dt class="font-semibold">¿Qué pasa si se agota el cupo de IA del mes?</dt>
                <dd class="m-0 text-neutral-800">Nada se bloquea: las nuevas postulaciones se evalúan con el análisis básico (palabras clave, también con evidencia) hasta que el cupo se renueva el primer día del mes.</dd>
            </div>
            <div>
                <dt class="font-semibold">¿Qué pasa si bajo de plan?</dt>
                <dd class="m-0 text-neutral-800">Conservas tus vacantes, usuarios y procesos aunque superen el nuevo límite; solo no podrás sumar más hasta estar dentro de él.</dd>
            </div>
            <div>
                <dt class="font-semibold">¿Cómo cambio de plan?</dt>
                <dd class="m-0 text-neutral-800">Desde «Mi empresa». El equipo de TalentCloud te escribe para coordinar el pago y lo aplica.</dd>
            </div>
        </dl>
    </section>
</div>

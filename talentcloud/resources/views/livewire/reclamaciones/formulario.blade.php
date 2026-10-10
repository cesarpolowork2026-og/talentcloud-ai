{{--
    Libro de Reclamaciones virtual (Ley 29571 y su reglamento): datos del proveedor, la hoja
    (consumidor, servicio y detalle) y, al presentarla, su número y la fecha límite de respuesta.
--}}
@use('App\Enums\TipoDocumento')
@use('App\Enums\TipoReclamacion')
<div class="mx-auto max-w-[860px]">
    <div class="mb-6 flex items-start gap-4">
        <span class="grid h-14 w-14 shrink-0 place-items-center rounded-lg bg-accent-100 text-accent-700" aria-hidden="true">
            <x-ui.icon name="book-open-text" :size="30" />
        </span>
        <x-ui.page-header class="!mb-0" title="Libro de Reclamaciones" subtitle="Conforme al Código de Protección y Defensa del Consumidor (Ley N.º 29571), contamos con un Libro de Reclamaciones a tu disposición." compact />
    </div>

    {{-- Proveedor: razón social, RUC y domicilio (config talentcloud.legal; RUC y domicilio, si están definidos). --}}
    <section class="mb-6 rounded border border-divider bg-surface p-4 text-14" aria-labelledby="proveedor">
        <h2 id="proveedor" class="mb-2 text-15">Datos del proveedor</h2>
        <dl class="m-0 grid gap-x-6 gap-y-1 sm:grid-cols-[auto_1fr]">
            <dt class="font-semibold">Razón social</dt><dd class="m-0">{{ $legal['responsable'] }}</dd>
            @if ($legal['ruc'])
                <dt class="font-semibold">RUC</dt><dd class="m-0">{{ $legal['ruc'] }}</dd>
            @endif
            @if ($legal['domicilio'])
                <dt class="font-semibold">Domicilio</dt><dd class="m-0">{{ $legal['domicilio'] }}</dd>
            @endif
            <dt class="font-semibold">Correo</dt><dd class="m-0"><a href="mailto:{{ config('talentcloud.soporte.correo') }}">{{ config('talentcloud.soporte.correo') }}</a></dd>
        </dl>
    </section>

    @if ($registrada)
        <x-ui.card elev="sm" class="!bg-white" role="status">
            <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:items-start sm:text-left">
                <x-ilustracion nombre="hoja-enviada" class="w-[160px] shrink-0" />
                <div class="grid gap-2">
                    <h2 class="m-0 text-20">Recibimos tu hoja {{ $registrada }}</h2>
                    <p class="m-0 text-15">Te enviamos una copia a <strong>{{ $correo }}</strong>. Te responderemos a ese correo a más tardar el <strong>{{ $fechaLimite }}</strong> ({{ $diasHabiles }} días hábiles).</p>
                    <p class="m-0 text-14 text-neutral-700">Guarda el número de tu hoja. Si no ves el correo, revisa la carpeta de spam o escríbenos a {{ config('talentcloud.soporte.correo') }}.</p>
                </div>
            </div>
        </x-ui.card>
    @else
        <form wire:submit="presentar" class="grid gap-6" novalidate>
            {{-- 1 · Consumidor --}}
            <x-ui.card elev="sm" class="!bg-white">
                <h2 class="m-0 text-17">1 · Identificación del consumidor</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Nombre completo" for="lr-nombre" error="form.nombre" class="sm:col-span-2">
                        <x-ui.input wire:model="form.nombre" id="lr-nombre" error="form.nombre" autocomplete="name" required />
                    </x-ui.field>
                    <x-ui.field label="Tipo de documento" for="lr-tipo-documento" error="form.tipoDocumento">
                        <x-ui.select wire:model="form.tipoDocumento" id="lr-tipo-documento" :options="TipoDocumento::opciones()" class="w-full" error="form.tipoDocumento" />
                    </x-ui.field>
                    <x-ui.field label="Número de documento" for="lr-numero-documento" error="form.numeroDocumento">
                        <x-ui.input wire:model="form.numeroDocumento" id="lr-numero-documento" error="form.numeroDocumento" required />
                    </x-ui.field>
                    <x-ui.field label="Domicilio" for="lr-domicilio" error="form.domicilio" class="sm:col-span-2">
                        <x-ui.input wire:model="form.domicilio" id="lr-domicilio" error="form.domicilio" autocomplete="street-address" required />
                    </x-ui.field>
                    <x-ui.field label="Correo electrónico" for="lr-email" error="form.email" hint="Aquí recibirás la copia de tu hoja y la respuesta.">
                        <x-ui.input wire:model="form.email" id="lr-email" type="email" error="form.email" autocomplete="email" required />
                    </x-ui.field>
                    <x-ui.field label="Teléfono (opcional)" for="lr-telefono" error="form.telefono">
                        <x-ui.input wire:model="form.telefono" id="lr-telefono" type="tel" error="form.telefono" autocomplete="tel" />
                    </x-ui.field>
                </div>

                <x-ui.checkbox wire:model.live="form.esMenor" id="lr-menor">Soy menor de edad</x-ui.checkbox>

                @if ($form->esMenor)
                    <fieldset class="m-0 grid gap-4 rounded border border-divider p-4 sm:grid-cols-2">
                        <legend class="px-1 text-14 font-semibold">Padre, madre o apoderado</legend>
                        <x-ui.field label="Nombre completo" for="lr-apoderado-nombre" error="form.apoderadoNombre" class="sm:col-span-2">
                            <x-ui.input wire:model="form.apoderadoNombre" id="lr-apoderado-nombre" error="form.apoderadoNombre" required />
                        </x-ui.field>
                        <x-ui.field label="Tipo de documento" for="lr-apoderado-tipo" error="form.apoderadoTipoDocumento">
                            <x-ui.select wire:model="form.apoderadoTipoDocumento" id="lr-apoderado-tipo" :options="TipoDocumento::opciones()" class="w-full" error="form.apoderadoTipoDocumento" />
                        </x-ui.field>
                        <x-ui.field label="Número de documento" for="lr-apoderado-numero" error="form.apoderadoNumeroDocumento">
                            <x-ui.input wire:model="form.apoderadoNumeroDocumento" id="lr-apoderado-numero" error="form.apoderadoNumeroDocumento" required />
                        </x-ui.field>
                    </fieldset>
                @endif
            </x-ui.card>

            {{-- 2 · Servicio contratado --}}
            <x-ui.card elev="sm" class="!bg-white">
                <h2 class="m-0 text-17">2 · Identificación del servicio contratado</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Servicio" for="lr-servicio" error="form.servicio">
                        <x-ui.select wire:model="form.servicio" id="lr-servicio" :options="$servicios" placeholder="Elige el servicio" class="w-full" error="form.servicio" />
                    </x-ui.field>
                    <x-ui.field label="Monto reclamado en soles (opcional)" for="lr-monto" error="form.monto">
                        <x-ui.input wire:model="form.monto" id="lr-monto" type="number" min="0" step="0.01" inputmode="decimal" error="form.monto" />
                    </x-ui.field>
                </div>
            </x-ui.card>

            {{-- 3 · Detalle --}}
            <x-ui.card elev="sm" class="!bg-white">
                <h2 class="m-0 text-17">3 · Detalle de la reclamación y pedido del consumidor</h2>
                <fieldset class="m-0 grid gap-2 border-0 p-0">
                    <legend class="mb-1 text-14 font-semibold text-neutral-800">Tipo</legend>
                    @foreach (TipoReclamacion::cases() as $tipo)
                        <x-ui.radio wire:model="form.tipo" name="tipo" value="{{ $tipo->value }}">
                            <strong>{{ $tipo->etiqueta() }}:</strong> {{ $tipo->definicion() }}
                        </x-ui.radio>
                    @endforeach
                    @error('form.tipo')
                        <p class="field-error m-0" role="alert">{{ $message }}</p>
                    @enderror
                </fieldset>
                <x-ui.field label="Detalle" for="lr-detalle" error="form.detalle" hint="Cuenta qué pasó, cuándo y con qué servicio.">
                    <x-ui.textarea wire:model="form.detalle" id="lr-detalle" rows="5" maxlength="3000" error="form.detalle" required />
                </x-ui.field>
                <x-ui.field label="Pedido" for="lr-pedido" error="form.pedido" hint="Qué solución esperas.">
                    <x-ui.textarea wire:model="form.pedido" id="lr-pedido" rows="3" maxlength="2000" error="form.pedido" required />
                </x-ui.field>
            </x-ui.card>

            {{-- Campo trampa para bots: fuera de la vista y del orden de tabulación. --}}
            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                <label for="lr-web">No completes este campo</label>
                <input wire:model="web" id="lr-web" type="text" tabindex="-1" autocomplete="off">
            </div>

            <div class="grid gap-3">
                {{-- Sin x-ui.field: su estilo de etiqueta (.field > label) achicaría el texto de la casilla. --}}
                <div class="grid gap-1">
                    <x-ui.checkbox wire:model="form.acepta" id="lr-acepta" aria-describedby="lr-acepta-ayuda">Declaro que los datos son verdaderos y que leí la Política de privacidad.</x-ui.checkbox>
                    <p id="lr-acepta-ayuda" class="m-0 text-13 text-neutral-700">Lee la <a href="{{ route('privacidad') }}" target="_blank" rel="noopener">Política de privacidad</a>: tus datos se usan solo para atender esta hoja.</p>
                    @error('form.acepta')
                        <p class="field-error m-0" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <ul class="m-0 grid gap-1 pl-5 text-13 text-neutral-700 list-disc">
                    <li>Te responderemos por correo en un plazo no mayor a {{ $diasHabiles }} días hábiles.</li>
                    <li>La formulación del reclamo no impide acudir a otras vías de solución de controversias ni es requisito previo para interponer una denuncia ante el INDECOPI.</li>
                </ul>

                <div>
                    <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="presentar">Enviar hoja de reclamación</x-ui.button>
                </div>
            </div>
        </form>
    @endif
</div>

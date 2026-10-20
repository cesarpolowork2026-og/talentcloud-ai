{{-- Términos de uso. Coherentes con lo que hace la aplicación (planes, decisión humana). --}}
<div>
    <x-legal.documento titulo="Términos de uso">
        <p>Estos términos regulan el uso de TalentCloud AI por parte de las empresas, su personal de selección y los postulantes. Al crear una cuenta los aceptas, junto con la <a href="{{ route('privacidad') }}">Política de privacidad</a>.</p>

        <h2 id="servicio">1. El servicio</h2>
        <p>TalentCloud AI permite a las empresas publicar vacantes, recibir y registrar candidatos, gestionar su proceso de selección (tablero por vacante, entrevistas y evaluaciones) y revisar cada CV frente a los requisitos de la vacante. Los postulantes pueden subir su CV, postular a las vacantes abiertas y seguir el estado de sus postulaciones.</p>

        <h2 id="cuentas">2. Cuentas</h2>
        <ul>
            <li>Los datos de registro deben ser verdaderos, y cada persona es responsable de su contraseña y de lo que se haga con su cuenta.</li>
            <li>La cuenta de empresa administra a su equipo: invita y desactiva a sus reclutadores y responde por su uso de la plataforma.</li>
            <li>Podemos suspender una empresa o una cuenta que incumpla estos términos; sus datos se conservan mientras se resuelve.</li>
        </ul>

        <h2 id="uso">3. Uso aceptable</h2>
        <ul>
            <li>Las empresas solo publican vacantes reales y tratan los datos de los candidatos únicamente para sus procesos de selección.</li>
            <li>No está permitido usar la plataforma para discriminar a los candidatos por motivos prohibidos por la ley, como el origen, el sexo, la religión, la opinión, la condición económica o la discapacidad.</li>
            <li>Quien registra a un candidato sin cuenta declara haber obtenido su consentimiento para hacerlo.</li>
        </ul>

        <h2 id="decisiones">4. Las personas deciden</h2>
        <p>Toda decisión de avanzar, descartar o contratar la toma una persona de la empresa, que es responsable de ella. La plataforma ordena la información de cada postulación, pero no evalúa ni decide por nadie.</p>

        <h2 id="planes">5. Planes y pagos</h2>
        <ul>
            <li>Los postulantes no pagan.</li>
            <li>Las empresas eligen un plan según sus vacantes activas y usuarios. Los precios están publicados en <a href="{{ route('planes') }}">Planes y precios</a>, en soles y sin IGV.</li>
            <li>Toda empresa empieza en el plan Gratis con {{ \App\Enums\PlanEmpresa::DIAS_DE_PRUEBA }} días del plan Pro. Al terminar la prueba, o al bajar de plan, se conserva todo lo existente, pero no se puede sumar por encima de los límites del plan.</li>
            @if (\App\Pagos\CobroEnLinea::habilitado())
                <li>Starter y Pro se pagan por adelantado con tarjeta, a través de Stripe, cada mes o cada año según lo elegido, más IGV. El plan cambia cuando Stripe confirma el pago. Si pagas durante la prueba, la prueba termina ese día.</li>
                <li>La suscripción se renueva automáticamente al final de cada periodo hasta que la canceles en «Mi empresa» → «Gestionar suscripción». Al cancelar, conservas el plan hasta el final del periodo ya pagado, no se vuelve a cobrar y después la empresa pasa al plan Gratis.</li>
                <li>Si cambias de plan en «Gestionar suscripción», el importe se ajusta en proporción al tiempo que queda del periodo.</li>
                <li>Si un cobro falla, te avisamos y se reintenta durante los días siguientes. Si no se completa, la suscripción se cancela y la empresa pasa al plan Gratis.</li>
                <li>Stripe envía un recibo de cada cobro. Ese recibo no es un comprobante de pago electrónico de SUNAT.</li>
                <li>El plan Enterprise se cotiza: se solicita desde «Mi empresa» y lo aplica el equipo de TalentCloud AI una vez coordinado el pago.</li>
            @else
                <li>El cambio de plan se solicita desde «Mi empresa» y lo aplica el equipo de TalentCloud AI una vez coordinado el pago.</li>
            @endif
        </ul>

        <h2 id="contenido">6. Contenido de cada usuario</h2>
        <p>Cada empresa es dueña de sus vacantes, notas y evaluaciones, y cada postulante de su CV. Nos autorizan a tratarlos solo para prestar el servicio, como se explica en la Política de privacidad.</p>

        <h2 id="responsabilidad">7. Disponibilidad y responsabilidad</h2>
        <p>Trabajamos para que la plataforma esté disponible y segura, pero puede haber interrupciones por mantenimiento o fallas. TalentCloud AI no es parte de la relación laboral entre empresas y postulantes, ni garantiza el resultado de un proceso de selección.</p>

        <h2 id="cambios">8. Cambios y ley aplicable</h2>
        <p>Si cambiamos estos términos, actualizaremos la fecha de arriba y avisaremos de los cambios importantes. Se rigen por las leyes de la República del Perú.</p>
    </x-legal.documento>
</div>

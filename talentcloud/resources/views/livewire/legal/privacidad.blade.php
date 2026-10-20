{{--
    Política de privacidad (Ley 29733). Cada afirmación corresponde a lo que hace la aplicación:
    si cambia el tratamiento de datos, se cambia este texto y config('talentcloud.legal.actualizado').
--}}
<div>
    <x-legal.documento titulo="Política de privacidad">
        <p>TalentCloud AI es una plataforma de reclutamiento: las empresas publican vacantes y gestionan sus procesos de selección, y los postulantes suben su CV y postulan. Esta política explica qué datos personales tratamos, para qué, quién los ve, cuánto tiempo los conservamos y cómo puedes ejercer tus derechos, conforme a la Ley N.º 29733, Ley de Protección de Datos Personales del Perú.</p>

        <h2 id="datos">1. Qué datos tratamos</h2>
        <p><strong>Si eres postulante:</strong></p>
        <ul>
            <li>Datos de tu cuenta: nombre, correo electrónico y contraseña (guardada cifrada, nadie puede leerla).</li>
            <li>Datos de tu perfil, si los indicas: teléfono y titular profesional.</li>
            <li>Tu CV en PDF o Word, su texto y lo que se obtiene de él: competencias, años de experiencia y certificaciones.</li>
            <li>Tus postulaciones: a qué vacante, en qué etapa está cada una y sus entrevistas (fecha, modalidad y lugar o enlace).</li>
        </ul>
        <p><strong>Si trabajas en una empresa cliente</strong> (cuenta de empresa o reclutador): tu nombre, correo, rol y las acciones que realizas en la plataforma (por ejemplo, «programó una entrevista»).</p>
        @if (\App\Pagos\CobroEnLinea::habilitado())
            <p><strong>Si tu empresa paga un plan en línea:</strong> el pago se hace en la página de Stripe, que recibe y guarda los datos de la tarjeta; TalentCloud AI nunca los ve. A Stripe le enviamos el nombre de la empresa y el correo de quien paga, y de Stripe guardamos solo los identificadores del cliente y de la suscripción, el plan, si es mensual o anual, su estado y su fecha de renovación o de término.</p>
        @endif
        <p><strong>Datos técnicos:</strong> la dirección IP de cada intento de inicio de sesión o de recuperación de contraseña, solo para limitar intentos y proteger las cuentas, y las cookies necesarias para mantener tu sesión (ver el punto 8).</p>

        <h2 id="finalidad">2. Para qué los usamos</h2>
        <ul>
            <li>Gestionar tus postulaciones y los procesos de selección de las empresas.</li>
            <li>Leer el texto de tu CV para que el equipo de selección de cada empresa pueda revisarlo y anotar sus competencias.</li>
            <li>Avisarte por correo y en la aplicación de los cambios en tus postulaciones y entrevistas.</li>
            <li>Mantener la seguridad de la plataforma y de las cuentas.</li>
        </ul>
        <p>No usamos tus datos con fines publicitarios ni los vendemos.</p>

        <h2 id="decisiones">3. Decisiones sobre tu postulación</h2>
        <p>Avanzar, descartar o contratar a un candidato lo decide siempre <strong>una persona</strong> de la empresa: la plataforma no toma decisiones automáticas sobre ti. El texto de tu CV no se envía a proveedores externos.</p>

        <h2 id="acceso">4. Quién ve tus datos</h2>
        <ul>
            <li><strong>Las empresas a cuyas vacantes postulas</strong>, o que te registraron en un proceso, y solo su personal de selección. Una empresa no ve tus postulaciones a otras empresas.</li>
            <li>Cada empresa escribe sus propias notas internas y evaluaciones de tu proceso; las demás empresas no las ven.</li>
            <li>El equipo de administración de TalentCloud AI, para dar soporte y supervisar el servicio.</li>
            <li>Nuestros proveedores de infraestructura (alojamiento en Google Cloud y envío de correos), que tratan los datos por nuestro encargo y con sus propias garantías de seguridad. Los servidores pueden estar fuera del Perú.</li>
            @if (\App\Pagos\CobroEnLinea::habilitado())
                <li>Stripe, procesador de los pagos de los planes, solo con los datos de pago de las empresas descritos en el punto 1. Stripe los trata en Estados Unidos y en otros países (transferencia internacional de datos) con sus propias garantías de seguridad.</li>
            @endif
        </ul>
        <p>Tus datos de postulante nunca son públicos. Lo único público son las vacantes abiertas que las empresas publican en la bolsa de empleos.</p>

        <h2 id="registrados">5. Si una empresa te registró</h2>
        <p>Una empresa puede registrar a un candidato con su CV aunque este no tenga cuenta. En ese caso, la empresa es responsable de haber obtenido su consentimiento, y TalentCloud AI trata esos datos por encargo de ella. Si fue tu caso y quieres acceder a tus datos o pedir que se eliminen, escríbenos al contacto indicado arriba.</p>

        <h2 id="conservacion">6. Cuánto tiempo los conservamos</h2>
        <p>Mientras tu cuenta exista. Cuando la eliminas, borramos tu cuenta, tu perfil de candidato, tus CV (también los archivos), tus postulaciones con sus entrevistas y evaluaciones, y los avisos y la actividad que te nombran. Si tenías entrevistas pendientes, la empresa recibe un aviso de que se cancelaron, sin tu nombre.</p>

        <h2 id="derechos">7. Tus derechos</h2>
        <p>Puedes ejercer tus derechos de acceso, rectificación, cancelación y oposición:</p>
        <ul>
            <li><strong>Acceso:</strong> en «Mi perfil», «Descargar mis datos» te entrega tu cuenta, tu CV, las competencias extraídas y tus postulaciones. Las notas internas y evaluaciones las redacta cada empresa como parte de su proceso; para acceder a ellas, escríbenos y lo gestionamos con la empresa.</li>
            <li><strong>Rectificación:</strong> corrige tu nombre, teléfono o titular en «Mi perfil», y sube un CV nuevo en «Mis postulaciones».</li>
            <li><strong>Cancelación:</strong> «Eliminar mi cuenta» en «Mi perfil» borra tus datos como se describe en el punto 6.</li>
            <li><strong>Oposición y revocación del consentimiento:</strong> puedes retirar cualquier postulación o eliminar tu cuenta cuando quieras.</li>
        </ul>
        <p>También puedes escribirnos al contacto indicado arriba. Si consideras que no atendimos tu solicitud, puedes presentar un reclamo ante la Autoridad Nacional de Protección de Datos Personales del Ministerio de Justicia y Derechos Humanos.</p>

        <h2 id="seguridad">8. Seguridad y cookies</h2>
        <ul>
            <li>Los datos de cada empresa están aislados: ninguna ve los de otra.</li>
            <li>Los CV se guardan en un almacenamiento privado, sin dirección pública, y solo se descargan con permiso.</li>
            <li>Las contraseñas se guardan cifradas; limitamos los intentos de inicio de sesión y te avisamos por correo si tu contraseña cambia.</li>
            <li>Solo usamos cookies necesarias: la de tu sesión, la de protección de formularios y, si eliges «Recordarme», la que mantiene tu sesión abierta. No usamos cookies de publicidad ni de analítica.</li>
        </ul>

        <h2 id="otros">9. Menores de edad y cambios</h2>
        <p>TalentCloud AI está dirigido a personas mayores de edad. Si cambiamos esta política, actualizaremos la fecha de arriba y, si el cambio es importante, te avisaremos por correo.</p>
    </x-legal.documento>
</div>

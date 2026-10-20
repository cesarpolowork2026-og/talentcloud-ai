<?php

use App\Enums\EstadoDeMiPostulacion;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

it('muestra a los invitados la presentación con el login en la raíz', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeVolt('pages.auth.login')
        ->assertSee('Todo tu reclutamiento en un solo lugar')
        ->assertSee('Iniciar sesión')
        ->assertSee(['Características', 'Beneficios', 'Seguridad'])
        ->assertSee('aria-roledescription="carrusel"', escape: false)
        ->assertSee('<title>Software de reclutamiento en Perú · TalentCloud AI</title>', escape: false);
});

it('muestra la misma presentación en /login, con el foco en el correo', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Todo tu reclutamiento en un solo lugar')
        ->assertSee('<title>Iniciar sesión · TalentCloud AI</title>', escape: false)
        ->assertSee('autofocus', escape: false);
});

it('lleva al personal de selección de la raíz y del login al dashboard', function (string $ruta) {
    $this->actingAs(User::factory()->reclutador()->create())
        ->get($ruta)
        ->assertRedirect(route('dashboard'));
})->with(['/', '/login']);

it('lleva al postulante de la raíz y del login a sus postulaciones, no a un dashboard prohibido', function (string $ruta) {
    $this->actingAs(User::factory()->postulante()->create())
        ->get($ruta)
        ->assertRedirect(route('postulante.postulaciones'));
})->with(['/', '/login']);

it('usa la misma cabecera y pie en todas las pantallas públicas', function (string $ruta, int $estado) {
    $this->get($ruta)
        ->assertStatus($estado)
        ->assertSee('aria-label="TalentCloud AI, inicio"', escape: false)
        ->assertSee(route('inicio').'#caracteristicas', escape: false)
        ->assertSee('aria-label="Pie de página"', escape: false);
})->with([
    'presentación' => ['/', 200],
    'crear empresa' => ['/register', 200],
    'registro de postulante' => ['/registro/postulante', 200],
    'recuperar contraseña' => ['/forgot-password', 200],
    'invitación' => ['/invitacion/token-inexistente', 200],
    'página no encontrada' => ['/no-existe', 404],
]);

it('dirige el mensaje del registro de postulante al candidato', function () {
    $this->get('/registro/postulante')->assertSee('Postula con tu CV y sigue cada proceso.');
    $this->get('/register')->assertSee('Encuentra el talento que necesitas.');
});

it('abre el inicio de sesión en una ventana con el fondo desenfocado, cerrada en la raíz', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('dialog-backdrop dialog-backdrop-desenfocado')
        ->toContain('role="dialog"')
        // "Iniciar sesión" de la cabecera abre la ventana en lugar de desplazar la página.
        ->toContain("x-on:click.prevent=\"\$dispatch('open-dialog', 'acceso')\"")
        // En la raíz no se abre sola.
        ->not->toContain('open = true)');
});

it('abre la ventana de inicio de sesión al cargar /login', function () {
    // Ahí llevan el middleware auth, el cierre de sesión y "Postular" de la bolsa de empleos.
    expect($this->get('/login')->assertOk()->getContent())->toContain('open = true)');
});

it('no tiene botón de pausa: el carrusel solo se detiene al pulsar sus flechas o puntos', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('Pausar el carrusel')
        // Ni el puntero ni el foco lo pausan: solo las flechas y los puntos.
        ->assertDontSee('suspender(', escape: false)
        ->assertSee('aria-label="Pantalla siguiente"', escape: false);
});

it('invita a actuar desde el primer vistazo: crear empresa gratis o buscar empleo', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['Todo tu reclutamiento en un solo lugar', 'Crear empresa gratis', 'Buscar empleo', 'días del plan Pro al empezar'])
        // Sin la fila de módulos que repetía las características.
        ->assertDontSee('Organiza y da seguimiento a cada postulación.');
});

it('muestra cada característica junto a la pantalla que la ilustra', function () {
    $this->get('/')
        ->assertSee(['Candidatos y CV, en un solo lugar', 'Del primer CV a la contratación', 'El proceso de un vistazo']);
});

it('enlaza desde el pie los planes y los documentos legales en todas las pantallas públicas', function (string $ruta) {
    $this->get($ruta)
        ->assertOk()
        ->assertSee('href="'.route('privacidad').'"', escape: false)
        ->assertSee('href="'.route('terminos').'"', escape: false)
        ->assertSee('href="'.route('planes').'"', escape: false);
})->with(['/', '/empleos', '/register', '/registro/postulante']);

it('publica la política de privacidad y los términos con los datos del responsable', function () {
    config(['talentcloud.legal.responsable' => 'Talent Cloud S.A.C.', 'talentcloud.legal.contacto' => 'privacidad@talentcloud.pe', 'talentcloud.legal.ruc' => null]);

    $this->get('/privacidad')
        ->assertOk()
        ->assertSee('Política de privacidad')
        ->assertSee('Ley N.º 29733')
        ->assertSee('Talent Cloud S.A.C.')
        ->assertSee('mailto:privacidad@talentcloud.pe', escape: false)
        ->assertSee('Descargar mis datos')
        ->assertDontSee('RUC');

    $this->get('/terminos')
        ->assertOk()
        ->assertSee('Términos de uso')
        ->assertSee('Las personas deciden')
        ->assertDontSee('inteligencia artificial');
});

it('enlaza la política de privacidad junto al consentimiento del postulante', function () {
    $this->get('/registro/postulante')
        ->assertSee('Lee la <a href="'.route('privacidad').'"', escape: false);
});

it('explica en tres pasos cómo funciona, sin prometer una IA que no existe', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('id="como-funciona"')
        ->toContain('De la vacante a la decisión, en tres pasos')
        ->toContain('href="#como-funciona"')
        ->and(Str::between($html, 'id="como-funciona"', 'id="caracteristicas"'))->not->toContain('IA');
});

it('la portada y las páginas públicas no mencionan IA, matching ni porcentajes de coincidencia', function (string $ruta) {
    $html = $this->get($ruta)->assertOk()->getContent();

    expect($html)->not->toContain('con IA')
        ->and($html)->not->toContain('La IA ')
        ->and($html)->not->toContain('matching')
        ->and($html)->not->toContain('% match');
})->with(['/', '/empleos', '/planes', '/privacidad', '/terminos', '/registro/postulante']);

it('pinta las ilustraciones decorativas ocultas y las que comunican algo con su descripción', function () {
    $decorativa = Blade::render('<x-ilustracion nombre="conector-horizontal" />');
    $conTexto = Blade::render('<x-ilustracion nombre="conector-vertical" descripcion="Une el CV con el requisito" />');

    expect($decorativa)->toContain('aria-hidden="true"')->not->toContain('role="img"')
        ->and($conTexto)->toContain('role="img"')->toContain('<title id="ilu-conector-vertical-')->toContain('Une el CV con el requisito');

    expect(fn () => Blade::render('<x-ilustracion nombre="no-existe" />'))->toThrow(Exception::class);
});

it('muestra la vista del postulante en un teléfono, con los estados que ve en la aplicación', function () {
    $html = $this->get('/')->assertOk()->getContent();
    $seccion = Str::between($html, 'id="postulantes"', '</section>');

    expect($seccion)->toContain('Tu búsqueda de empleo, en orden y en tu celular')
        ->toContain('Mis postulaciones')
        ->toContain('href="'.route('register.postulante').'"');

    // Mismas etiquetas que EstadoDeMiPostulacion (la imagen no promete otra cosa).
    foreach ([EstadoDeMiPostulacion::Entrevista, EstadoDeMiPostulacion::EnRevision, EstadoDeMiPostulacion::NoSeleccionado] as $estado) {
        expect($seccion)->toContain('>'.$estado->etiqueta().'<');
    }
});

it('usa el mismo teléfono en el registro de postulante', function () {
    $this->get('/registro/postulante')->assertOk()->assertSee('Próxima entrevista')->assertSee('cv_andrea.pdf');
    $this->get('/register')->assertOk()->assertDontSee('cv_andrea.pdf');
});

it('ofrece vista previa al compartir las páginas públicas, pero no las que llevan un token', function () {
    $this->get('/')->assertOk()
        ->assertSee('<meta property="og:image" content="'.asset('marca/vista-previa.png').'">', escape: false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', escape: false);
    $this->get('/empleos')->assertOk()->assertSee('property="og:title" content="Empleos · TalentCloud AI"', escape: false);
    $this->get('/registro/postulante')->assertOk()->assertSee('Postula con tu CV y sigue cada proceso', escape: false);

    $this->get('/forgot-password')->assertOk()->assertDontSee('og:image', escape: false);
    expect(file_exists(public_path('marca/vista-previa.png')))->toBeTrue();
});

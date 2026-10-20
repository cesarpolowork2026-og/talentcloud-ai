<?php

use App\Enums\EstadoVacante;
use App\Enums\Modalidad;
use App\Enums\TipoRequisito;
use App\Models\Competencia;
use App\Models\Empresa;
use App\Models\Requisito;
use App\Models\Vacante;
use App\Support\DatosEstructurados;

/** Los bloques JSON-LD del HTML, ya decodificados. */
function jsonldDe(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $bloques);

    return array_map(fn (string $b) => json_decode($b, true, flags: JSON_THROW_ON_ERROR), $bloques[1]);
}

it('la ficha de una vacante presencial lleva su JobPosting con lugar, empresa y requisitos', function () {
    $empresa = Empresa::factory()->create(['nombre' => 'Altura Café', 'sitio_web' => 'https://alturacafe.pe']);
    $vacante = Vacante::factory()->deEmpresa($empresa)->create([
        'titulo' => 'Barista', 'descripcion' => "Preparas café de especialidad.\n\nAtiendes en barra.", 'modalidad' => Modalidad::Presencial, 'ubicacion' => 'Lima',
    ]);
    Requisito::factory()->create(['vacante_id' => $vacante->id, 'competencia_id' => Competencia::factory()->create(['nombre' => 'Latte art'])->id, 'tipo' => TipoRequisito::Obligatorio]);

    $bloques = jsonldDe($this->get(route('empleos.show', $vacante->id))->assertOk()->getContent());

    expect($bloques)->toHaveCount(1);
    $oferta = $bloques[0];
    expect($oferta['@type'])->toBe('JobPosting')
        ->and($oferta['title'])->toBe('Barista')
        ->and($oferta['hiringOrganization']['name'])->toBe('Altura Café')
        ->and($oferta['hiringOrganization']['sameAs'])->toBe('https://alturacafe.pe')
        ->and($oferta['jobLocation']['address'])->toBe(['@type' => 'PostalAddress', 'addressLocality' => 'Lima', 'addressCountry' => 'PE'])
        ->and($oferta['description'])->toContain('<p>Preparas café de especialidad.</p>')->toContain('<li>Latte art (obligatorio)</li>')
        ->and($oferta['datePosted'])->not->toBeEmpty()
        ->and($oferta)->not->toHaveKeys(['jobLocationType', 'baseSalary', 'validThrough']);
});

it('una vacante remota se declara como teletrabajo con su cobertura', function () {
    $vacante = Vacante::factory()->deEmpresa(Empresa::factory()->create())->create(['modalidad' => Modalidad::Remoto, 'ubicacion' => null]);

    $oferta = jsonldDe($this->get(route('empleos.show', $vacante->id))->getContent())[0];

    expect($oferta['jobLocationType'])->toBe('TELECOMMUTE')
        ->and($oferta['applicantLocationRequirements'])->toBe(['@type' => 'Country', 'name' => 'Perú'])
        ->and($oferta)->not->toHaveKey('jobLocation');
});

it('un título o descripción con HTML no puede cerrar la etiqueta del script', function () {
    $vacante = Vacante::factory()->deEmpresa(Empresa::factory()->create())->create([
        'titulo' => 'Dev </script><script>alert(1)</script>', 'descripcion' => '<img src=x onerror=alert(1)>',
    ]);

    $html = $this->get(route('empleos.show', $vacante->id))->getContent();

    expect($html)->not->toContain('<script>alert(1)</script>');
    expect(jsonldDe($html)[0]['title'])->toBe('Dev </script><script>alert(1)</script>');
    expect(jsonldDe($html)[0]['description'])->toContain('&lt;img src=x onerror=alert(1)&gt;');
});

it('un sitio web que no es http(s) no se declara como sameAs', function () {
    $empresa = Empresa::factory()->create(['sitio_web' => 'javascript:alert(1)']);
    $vacante = Vacante::factory()->deEmpresa($empresa)->create();

    expect(DatosEstructurados::ofertaDeEmpleo($vacante->load('empresa', 'requisitos.competencia'))['hiringOrganization'])->not->toHaveKey('sameAs');
});

it('una vacante cerrada no entrega oferta de empleo', function () {
    $vacante = Vacante::factory()->deEmpresa(Empresa::factory()->create())->enEstado(EstadoVacante::Cerrada)->create();

    $this->get(route('empleos.show', $vacante->id))->assertNotFound();
});

it('la portada declara la organización y el sitio; /login y las demás páginas no', function () {
    $bloques = jsonldDe($this->get('/')->assertOk()->getContent());
    $tipos = array_column($bloques[0]['@graph'], '@type');

    expect($tipos)->toBe(['Organization', 'WebSite'])
        ->and($bloques[0]['@graph'][0]['contactPoint']['email'])->toBe(config('talentcloud.soporte.correo'))
        ->and($bloques[0]['@graph'][1]['publisher'])->toBe(['@id' => route('inicio').'#organizacion']);

    foreach (['/login', '/planes', '/privacidad', '/terminos', '/empleos'] as $ruta) {
        expect($this->get($ruta)->assertOk()->getContent())->not->toContain('application/ld+json');
    }
});

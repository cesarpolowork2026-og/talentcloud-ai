<?php

use App\Enums\EstadoVacante;
use App\Models\Empresa;
use App\Models\Vacante;

/*
 * API REST pública v1 (solo lectura): expone lo mismo que la bolsa de empleos y nada más.
 */

function vacanteApi(array $atributos = [], ?Empresa $empresa = null, EstadoVacante $estado = EstadoVacante::Abierta): Vacante
{
    return Vacante::factory()->deEmpresa($empresa ?? Empresa::factory()->create())->enEstado($estado)->create($atributos);
}

it('responde la salud de la API', function () {
    $this->getJson('/api/v1/salud')->assertOk()->assertJson(['estado' => 'ok', 'base_de_datos' => true, 'version' => 'v1']);
});

it('lista solo vacantes abiertas de empresas activas', function () {
    $abierta = vacanteApi(['titulo' => 'Analista de datos']);
    vacanteApi(['titulo' => 'Borrador secreto'], estado: EstadoVacante::Borrador);
    vacanteApi(['titulo' => 'Vacante cerrada'], estado: EstadoVacante::Cerrada);
    vacanteApi(['titulo' => 'De empresa suspendida'], Empresa::factory()->suspendida()->create());

    $respuesta = $this->getJson('/api/v1/vacantes')->assertOk();

    expect(collect($respuesta->json('data'))->pluck('id')->all())->toBe([$abierta->id])
        ->and($respuesta->json('meta.total'))->toBe(1);
});

it('no expone campos internos', function () {
    vacanteApi();

    $vacante = $this->getJson('/api/v1/vacantes')->assertOk()->json('data.0');

    expect(array_keys($vacante))->toEqualCanonicalizing(['id', 'titulo', 'modalidad', 'ubicacion', 'jornada', 'sueldo', 'publicada_en', 'empresa', 'requisitos', 'url'])
        ->and(array_keys($vacante['empresa']))->toEqualCanonicalizing(['nombre', 'sector', 'sitio_web']);
});

it('filtra por modalidad y por texto sin distinguir tildes', function () {
    $remota = vacanteApi(['titulo' => 'Desarrolladora Backend', 'modalidad' => 'remoto', 'ubicacion' => null]);
    vacanteApi(['titulo' => 'Cajero', 'modalidad' => 'presencial', 'ubicacion' => 'Lima']);

    $porModalidad = $this->getJson('/api/v1/vacantes?modalidad=remoto')->assertOk()->json('data');
    $porTexto = $this->getJson('/api/v1/vacantes?q=desarrolladora')->assertOk()->json('data');

    expect(collect($porModalidad)->pluck('id')->all())->toBe([$remota->id])
        ->and(collect($porTexto)->pluck('id')->all())->toBe([$remota->id]);
});

it('rechaza filtros inválidos con 422 en JSON', function () {
    $this->getJson('/api/v1/vacantes?modalidad=volando')->assertStatus(422)->assertJsonValidationErrors('modalidad');
    $this->getJson('/api/v1/vacantes?por_pagina=500')->assertStatus(422)->assertJsonValidationErrors('por_pagina');
});

it('el detalle incluye descripción y requisitos', function () {
    $vacante = vacanteApi(['descripcion' => 'Construirás APIs.']);

    $this->getJson("/api/v1/vacantes/{$vacante->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $vacante->id)
        ->assertJsonPath('data.descripcion', 'Construirás APIs.')
        ->assertJsonStructure(['data' => ['requisitos', 'empresa', 'sueldo']]);
});

it('responde 404 en JSON para una vacante que no es pública', function () {
    $borrador = vacanteApi(estado: EstadoVacante::Borrador);

    $this->getJson("/api/v1/vacantes/{$borrador->id}")->assertNotFound()->assertJson(['mensaje' => 'Vacante no encontrada.']);
    $this->getJson('/api/v1/vacantes/999999')->assertNotFound();
});

it('un error en /api responde JSON aunque no se pida', function () {
    $this->get('/api/v1/no-existe')->assertNotFound()->assertHeader('Content-Type', 'application/json');
});

it('limita las peticiones por IP', function () {
    foreach (range(1, 60) as $_) {
        $this->getJson('/api/v1/salud')->assertOk();
    }

    $this->getJson('/api/v1/salud')->assertStatus(429);
});

it('es de solo lectura', function () {
    $this->postJson('/api/v1/vacantes', ['titulo' => 'x'])->assertStatus(405);
    $this->deleteJson('/api/v1/vacantes/1')->assertStatus(405);
});

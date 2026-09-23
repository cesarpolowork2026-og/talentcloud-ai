<?php

use App\Enums\TipoRequisito;
use App\Matching\MatchInput;
use App\Matching\RequisitoInput;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Autentica al usuario y fija su contexto de empresa, como hace el middleware
 * del grupo web. Necesario con Livewire::test(), que no pasa por el middleware.
 */
function actuarComo(User $user): User
{
    test()->actingAs($user);
    app(TenantContext::class)->establecerParaUsuario($user);

    return $user;
}

/**
 * Ejemplo del contrato con el servicio de IA (talentcloud-ai/contrato/ejemplos),
 * el mismo que valida el pytest del servicio.
 *
 * @return array<string, mixed>
 */
function ejemploDeContrato(string $nombre): array
{
    $ruta = dirname(__DIR__, 2).'/talentcloud-ai/contrato/ejemplos/'.$nombre.'.json';

    return json_decode((string) file_get_contents($ruta), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * El MatchInput que corresponde a un ejemplo del contrato (match_input.json).
 *
 * @param  array<string, mixed>  $ejemplo
 */
function entradaDeEjemplo(array $ejemplo): MatchInput
{
    return new MatchInput(
        postulacionId: $ejemplo['postulacion_id'],
        vacante: $ejemplo['vacante']['titulo'],
        descripcionVacante: $ejemplo['vacante']['descripcion'],
        requisitos: array_map(fn (array $r) => new RequisitoInput(
            id: $r['id'],
            competencia: $r['competencia'],
            sinonimos: $r['sinonimos'],
            tipo: TipoRequisito::from($r['tipo']),
            peso: $r['peso'],
            aniosMinimos: $r['anios_minimos'],
            descripcion: $r['descripcion'],
        ), $ejemplo['requisitos']),
        cvId: $ejemplo['cv']['id'],
        textoCv: $ejemplo['cv']['texto'],
    );
}

<?php

use App\Enums\EtapaPostulacion;
use App\Enums\TipoRequisito;
use App\Livewire\Postulaciones\Detalle;
use App\Livewire\Seleccion\Central;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Cv;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\Requisito;
use App\Models\User;
use App\Models\Vacante;
use App\Services\Postulaciones\AnalisisBasicoDeRequisitos;
use App\Services\Postulaciones\PostularCandidato;
use App\Support\BuscadorDeTerminos;
use App\Tenancy\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    $this->empresa = Empresa::factory()->create(['nombre' => 'Nimbus Corp']);
    $this->marta = User::factory()->deEmpresa($this->empresa)->reclutador()->create();
    $this->vacante = Vacante::factory()->deEmpresa($this->empresa)->create(['titulo' => 'Desarrollador Backend']);

    $this->laravel = Competencia::factory()->create(['nombre' => 'Laravel', 'sinonimos' => []]);
    $this->postgres = Competencia::factory()->create(['nombre' => 'PostgreSQL', 'sinonimos' => ['Postgres']]);
    $this->docker = Competencia::factory()->create(['nombre' => 'Docker', 'sinonimos' => []]);
    $this->java = Competencia::factory()->create(['nombre' => 'Java', 'sinonimos' => []]);

    $requisito = fn (Competencia $c, TipoRequisito $tipo, int $orden) => Requisito::factory()->create([
        'vacante_id' => $this->vacante->id, 'competencia_id' => $c->id, 'tipo' => $tipo, 'orden' => $orden,
    ]);
    $requisito($this->laravel, TipoRequisito::Obligatorio, 0);
    $requisito($this->postgres, TipoRequisito::Obligatorio, 1);
    $requisito($this->docker, TipoRequisito::Obligatorio, 2);
    $requisito($this->java, TipoRequisito::Deseable, 3);

    $this->postular = function (string $nombre, ?string $cv, EtapaPostulacion $etapa = EtapaPostulacion::Postulado) {
        $candidato = Candidato::factory()->create(['nombre' => $nombre]);
        if ($cv !== null) {
            Cv::factory()->procesado($cv)->create(['candidato_id' => $candidato->id]);
        }

        return Postulacion::factory()->paraVacante($this->vacante)->create(['candidato_id' => $candidato->id, 'etapa' => $etapa, 'orden' => 0]);
    };

    actuarComo($this->marta);
});

it('cubre un requisito cuando el CV nombra la competencia o un sinónimo, con el fragmento como evidencia', function () {
    $p = ($this->postular)('Elena Ruiz', "Tres años con Laravel 12 y bases de datos Postgres en producción.\nSin contenedores.");

    $cobertura = app(AnalisisBasicoDeRequisitos::class)->paraCandidatos($this->vacante, [$p->candidato_id])[$p->candidato_id];

    expect($cobertura->total())->toBe(4)
        ->and($cobertura->cubiertos())->toBe(2)
        ->and($cobertura->obligatoriosFaltantes())->toBe(['Docker'])
        ->and($cobertura->items[0]['via'])->toBe('cv')
        ->and($cobertura->items[0]['fragmento'])->toContain('Laravel 12')
        ->and($cobertura->items[1]['fragmento'])->toContain('Postgres')
        ->and($cobertura->items[2]['cubierto'])->toBeFalse();
});

it('busca palabras completas, sin tildes ni mayúsculas', function () {
    expect(BuscadorDeTerminos::posiciones('Dominio de JavaScript y TypeScript', ['Java']))->toBe([])
        ->and(BuscadorDeTerminos::posiciones('Programación en JAVA.', ['Java']))->toBe([[16, 4]])
        ->and(BuscadorDeTerminos::posiciones('Gestión de administración', ['Administracion']))->toBe([[11, 14]])
        ->and(BuscadorDeTerminos::posiciones('Experiencia en C++ y C#', ['C++']))->toBe([[15, 3]])
        // Los términos de una o dos letras respetan las mayúsculas: «c» suelto no es el lenguaje C.
        ->and(BuscadorDeTerminos::posiciones('trabajo en c y c++', ['C']))->toBe([]);
});

it('da por cubierta una competencia que la empresa anotó, aunque el CV no la nombre', function () {
    $p = ($this->postular)('Marco Díaz', 'Desarrollador con experiencia general.');
    $p->candidato->todasLasCompetencias()->attach($this->docker->id, ['fuente' => 'manual', 'empresa_id' => $this->empresa->id]);

    $cobertura = app(AnalisisBasicoDeRequisitos::class)->paraCandidatos($this->vacante, [$p->candidato_id])[$p->candidato_id];

    expect($cobertura->items[2]['cubierto'])->toBeTrue()
        ->and($cobertura->items[2]['via'])->toBe('anotada')
        ->and($cobertura->items[2]['fragmento'])->toBeNull();
});

it('no usa las anotaciones de otra empresa', function () {
    $otra = Empresa::factory()->create();
    $p = ($this->postular)('Marco Díaz', 'Sin nada que ver.');
    $p->candidato->todasLasCompetencias()->attach($this->docker->id, ['fuente' => 'manual', 'empresa_id' => $otra->id]);

    $cobertura = app(AnalisisBasicoDeRequisitos::class)->paraCandidatos($this->vacante, [$p->candidato_id])[$p->candidato_id];

    expect($cobertura->items[2]['cubierto'])->toBeFalse();
});

it('usa el CV más reciente y avisa si no hay uno con texto', function () {
    $p = ($this->postular)('Sofía Nieto', null);

    $sin = app(AnalisisBasicoDeRequisitos::class)->paraCandidatos($this->vacante, [$p->candidato_id])[$p->candidato_id];
    expect($sin->conCv)->toBeFalse()->and($sin->cubiertos())->toBe(0);

    Cv::factory()->procesado('Antes usaba Docker.')->create(['candidato_id' => $p->candidato_id]);
    Cv::factory()->procesado('Ahora solo Java.')->create(['candidato_id' => $p->candidato_id]);

    $con = app(AnalisisBasicoDeRequisitos::class)->paraCandidatos($this->vacante, [$p->candidato_id])[$p->candidato_id];
    expect($con->conCv)->toBeTrue()->and($con->items[2]['cubierto'])->toBeFalse()->and($con->items[3]['cubierto'])->toBeTrue();
});

it('muestra en la tarjeta del tablero cuántos requisitos cubre y cuántos obligatorios faltan', function () {
    ($this->postular)('Elena Ruiz', 'Laravel y Postgres.');
    ($this->postular)('Pablo Vega', 'Laravel, Postgres, Docker y Java.', EtapaPostulacion::Descartado);

    Livewire::test(Central::class, ['vacante' => $this->vacante])
        ->assertSee('Cubre 2 de 4 requisitos · falta 1 obligatorio')
        // Las descartadas no llevan análisis.
        ->assertDontSee('Cubre 4 de 4 requisitos');
});

it('muestra el análisis en la ficha de la postulación y nunca mueve la etapa', function () {
    $p = ($this->postular)('Elena Ruiz', 'Trabajé con Laravel y Docker.');

    Livewire::test(Detalle::class, ['postulacion' => $p])
        ->assertSee('Cubre 2 de 4 requisitos')
        ->assertSee('falta PostgreSQL', escape: false)
        ->assertSee('Trabajé con Laravel y Docker.');

    expect($p->fresh()->etapa)->toBe(EtapaPostulacion::Postulado);
});

it('el aislamiento por empresa se mantiene: otra empresa no ve la postulación', function () {
    $p = ($this->postular)('Elena Ruiz', 'Laravel');
    $intrusa = User::factory()->deEmpresa(Empresa::factory()->create())->reclutador()->create();
    actuarComo($intrusa);

    Livewire::test(Detalle::class, ['postulacion' => $p])->assertForbidden();
});

it('el admin supervisa el análisis sin filtro de empresa', function () {
    $p = ($this->postular)('Elena Ruiz', 'Laravel');
    actuarComo(User::factory()->admin()->create());

    $cobertura = app(TenantContext::class)->ejecutarGlobal(
        fn () => app(AnalisisBasicoDeRequisitos::class)->paraCandidatos($this->vacante, [$p->candidato_id])[$p->candidato_id],
    );

    expect($cobertura->cubiertos())->toBe(1);
});

it('muestra el texto del CV con las competencias pedidas resaltadas', function () {
    $p = ($this->postular)('Elena Ruiz', "Experiencia sólida.\nUsé Laravel y Docker a diario.");

    // Livewire intercala comentarios de bloque; no se ven, pero separan los fragmentos en el HTML.
    $html = preg_replace('/<!--.*?-->/s', '', Livewire::test(Detalle::class, ['postulacion' => $p])->assertSee('Texto del CV')->html());

    expect($html)->toContain('<mark class="rounded-sm bg-accent-100 px-[2px] text-ink">Laravel</mark> y <mark class="rounded-sm bg-accent-100 px-[2px] text-ink">Docker</mark> a diario.');
});

it('explica cuando el CV no tiene texto legible', function () {
    $candidato = Candidato::factory()->create();
    Cv::factory()->conError()->create(['candidato_id' => $candidato->id]);
    $p = Postulacion::factory()->paraVacante($this->vacante)->create(['candidato_id' => $candidato->id, 'orden' => 0]);

    Livewire::test(Detalle::class, ['postulacion' => $p])->assertSee('No se pudo leer el texto de este CV');
});

it('el texto del CV se escapa: un CV con HTML no inyecta marcado', function () {
    $p = ($this->postular)('Elena Ruiz', 'Laravel <script>alert(1)</script> y Docker');

    $html = Livewire::test(Detalle::class, ['postulacion' => $p])->html();

    expect($html)->not->toContain('<script>alert(1)</script>')->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

// — El CV de una postulación es el que se envió, no el último que subió el candidato —

/** Una postulación enviada con un CV y, después, otro CV más reciente del mismo candidato. */
function postulacionConCvNuevoDespues(string $textoEnviado, string $textoNuevo): Postulacion
{
    $candidato = Candidato::factory()->create(['nombre' => 'Elena Ruiz']);
    $enviado = Cv::factory()->procesado($textoEnviado)->create(['candidato_id' => $candidato->id, 'nombre_original' => 'CV_enviado.pdf']);
    $postulacion = Postulacion::factory()->paraVacante(test()->vacante)->create(['candidato_id' => $candidato->id, 'cv_id' => $enviado->id, 'etapa' => EtapaPostulacion::Postulado, 'orden' => 0]);
    Cv::factory()->procesado($textoNuevo)->create(['candidato_id' => $candidato->id, 'nombre_original' => 'CV_nuevo.pdf']);

    return $postulacion;
}

it('la ficha de la postulación muestra el CV enviado y avisa que hay uno más reciente', function () {
    $p = postulacionConCvNuevoDespues('Trabajo con Laravel y Postgres.', 'Ahora solo Docker.');
    $enviado = Cv::find($p->cv_id);
    $nuevo = Cv::where('candidato_id', $p->candidato_id)->where('nombre_original', 'CV_nuevo.pdf')->firstOrFail();

    Livewire::test(Detalle::class, ['postulacion' => $p])
        ->assertSee('CV_enviado.pdf')
        ->assertSee('Es el CV con el que postuló')
        ->assertSee('CV_nuevo.pdf') // solo en el aviso
        ->assertSeeHtml(route('cvs.descargar', $enviado))
        ->assertDontSeeHtml(route('cvs.descargar', $nuevo))
        // El análisis y el texto salen del CV enviado: Laravel y PostgreSQL sí, Docker (del CV nuevo) no.
        ->assertSee('Cubre 2 de 4 requisitos')
        ->assertSee('Trabajo con Laravel y Postgres.')
        ->assertDontSee('Ahora solo Docker.');
});

it('el análisis del tablero usa el CV enviado de cada postulación', function () {
    $p = postulacionConCvNuevoDespues('Trabajo con Laravel y Postgres.', 'Ahora solo Docker.');

    $cobertura = app(AnalisisBasicoDeRequisitos::class)->paraCandidatos($this->vacante, [$p->candidato_id])[$p->candidato_id];

    expect($cobertura->cubiertos())->toBe(2)
        ->and($cobertura->items[2]['cubierto'])->toBeFalse(); // Docker solo está en el CV nuevo

    Livewire::test(Central::class, ['vacante' => $this->vacante])->assertSee('Cubre 2 de 4 requisitos');
});

it('si el CV enviado no tiene texto no se usa el de otro CV del candidato', function () {
    $candidato = Candidato::factory()->create();
    $ilegible = Cv::factory()->conError()->create(['candidato_id' => $candidato->id]);
    Postulacion::factory()->paraVacante($this->vacante)->create(['candidato_id' => $candidato->id, 'cv_id' => $ilegible->id, 'orden' => 0]);
    Cv::factory()->procesado('Laravel, Postgres, Docker y Java.')->create(['candidato_id' => $candidato->id]);

    $cobertura = app(AnalisisBasicoDeRequisitos::class)->paraCandidatos($this->vacante, [$candidato->id])[$candidato->id];

    expect($cobertura->conCv)->toBeFalse()->and($cobertura->cubiertos())->toBe(0);
});

it('una postulación sin CV registrado muestra el actual del candidato', function () {
    $candidato = Candidato::factory()->create();
    $cv = Cv::factory()->procesado('Laravel y Docker.')->create(['candidato_id' => $candidato->id, 'nombre_original' => 'CV_actual.pdf']);
    $p = Postulacion::factory()->paraVacante($this->vacante)->create(['candidato_id' => $candidato->id, 'cv_id' => null, 'orden' => 0]);

    Livewire::test(Detalle::class, ['postulacion' => $p])
        ->assertSee('CV_actual.pdf')
        ->assertDontSee('Es el CV con el que postuló')
        ->assertSeeHtml(route('cvs.descargar', $cv));
});

it('al postular queda registrado el último CV del candidato', function () {
    $candidato = Candidato::factory()->create();
    Cv::factory()->procesado('Antes.')->create(['candidato_id' => $candidato->id]);
    $ultimo = Cv::factory()->procesado('Ahora.')->create(['candidato_id' => $candidato->id]);

    $postulacion = app(PostularCandidato::class)->ejecutar($this->vacante, $candidato);

    expect($postulacion->cv_id)->toBe($ultimo->id);
});

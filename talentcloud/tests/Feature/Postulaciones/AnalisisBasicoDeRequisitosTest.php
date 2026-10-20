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

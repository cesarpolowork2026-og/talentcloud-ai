<?php

use App\Enums\EstadoVacante;
use App\Enums\EtapaPostulacion;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\Postulacion;
use App\Models\Vacante;
use App\Services\Postulaciones\PostularCandidato;
use App\Services\Vacantes\OperacionNoPermitida;
use Illuminate\Support\Facades\DB;

/*
 * Postular al mismo tiempo: el segundo envío recibe el mensaje de siempre, no un error del servidor, y las
 * postulaciones de una vacante no repiten su orden en la columna.
 */

beforeEach(function () {
    $this->empresa = Empresa::factory()->create();
    $this->vacante = Vacante::factory()->deEmpresa($this->empresa)->enEstado(EstadoVacante::Abierta)->create(['titulo' => 'Analista']);
    $this->candidato = Candidato::factory()->create(['nombre' => 'Marco Díaz']);
});

it('si otro envío gana la carrera, el segundo recibe «ya postuló» en lugar de un error 500', function () {
    // Simula la transacción simultánea: justo antes de insertar, otra ya guardó la misma postulación.
    Postulacion::creating(function (Postulacion $nueva) {
        static $ya = false;
        if (! $ya) {
            $ya = true;
            DB::table('postulaciones')->insert([
                'empresa_id' => $nueva->empresa_id, 'vacante_id' => $nueva->vacante_id, 'candidato_id' => $nueva->candidato_id,
                'etapa' => 'postulado', 'orden' => 0, 'origen' => 'equipo', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    });

    expect(fn () => app(PostularCandidato::class)->ejecutar($this->vacante, $this->candidato))
        ->toThrow(OperacionNoPermitida::class, 'Marco Díaz ya postuló a Analista.');
});

it('una vacante cerrada un instante antes no recibe la postulación', function () {
    // La vacante que llega al servicio aún figura abierta; en la base ya se cerró.
    $enMemoria = $this->vacante->replicate();
    $enMemoria->id = $this->vacante->id;
    $enMemoria->exists = true;
    DB::table('vacantes')->where('id', $this->vacante->id)->update(['estado' => 'cerrada']);

    expect(fn () => app(PostularCandidato::class)->ejecutar($enMemoria, $this->candidato))
        ->toThrow(OperacionNoPermitida::class, 'Solo se puede postular a vacantes abiertas.');

    expect(Postulacion::sinFiltroDeEmpresa()->where('vacante_id', $this->vacante->id)->exists())->toBeFalse();
});

it('cada postulación nueva queda al final de «Postulado» con un orden propio', function () {
    foreach (range(1, 4) as $_) {
        app(PostularCandidato::class)->ejecutar($this->vacante, Candidato::factory()->create());
    }

    $ordenes = Postulacion::sinFiltroDeEmpresa()->where('vacante_id', $this->vacante->id)
        ->where('etapa', EtapaPostulacion::Postulado)->orderBy('id')->pluck('orden')->all();

    expect($ordenes)->toBe([1, 2, 3, 4]);
});

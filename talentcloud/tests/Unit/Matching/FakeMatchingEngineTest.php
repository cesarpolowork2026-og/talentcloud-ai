<?php

use App\Enums\MotorMatching;
use App\Enums\NivelCoincidencia;
use App\Enums\TipoRequisito;
use App\Matching\FakeMatchingEngine;
use App\Matching\MatchInput;
use App\Matching\MatchResult;
use App\Matching\RequisitoInput;

function requisito(int $id, string $competencia, array $sinonimos = [], int $peso = 3, ?int $anios = null): RequisitoInput
{
    return new RequisitoInput($id, $competencia, $sinonimos, TipoRequisito::Obligatorio, $peso, $anios, null);
}

function evaluar(string $cv, array $requisitos): MatchResult
{
    return (new FakeMatchingEngine)->evaluar(new MatchInput(1, 'Desarrollador Backend', null, $requisitos, 1, $cv));
}

const CV_ELENA = <<<'TXT'
Elena Ruiz
Desarrolladora backend con 5 años de experiencia con Java en proyectos de alto tráfico.
Diseñé esquemas y consultas optimizadas en PostgreSQL.

Intereses
Kubernetes (interés en aprender) y arquitectura en la nube.
TXT;

it('reconoce una competencia con los años de experiencia exigidos', function () {
    $java = evaluar(CV_ELENA, [requisito(1, 'Java', anios: 3)])->requisitos[0];

    expect($java->nivel)->toBe(NivelCoincidencia::Coincide)
        ->and($java->porcentaje)->toBe(90)
        ->and($java->evidencia)->toContain('5 años de experiencia con Java');
});

it('baja la coincidencia si los años declarados no alcanzan el mínimo', function () {
    $java = evaluar(CV_ELENA, [requisito(1, 'Java', anios: 8)])->requisitos[0];

    expect($java->porcentaje)->toBe(50)
        ->and($java->nivel)->toBe(NivelCoincidencia::Revisar);
});

it('marca para revisar las menciones que no acreditan experiencia', function () {
    $kubernetes = evaluar(CV_ELENA, [requisito(1, 'Kubernetes', ['k8s'])])->requisitos[0];

    expect($kubernetes->nivel)->toBe(NivelCoincidencia::Revisar)
        ->and($kubernetes->porcentaje)->toBe(20)
        ->and($kubernetes->evidencia)->toContain('interés en aprender');
});

it('explica los requisitos sin mención en el CV', function () {
    $aws = evaluar(CV_ELENA, [requisito(1, 'Certificación AWS')])->requisitos[0];

    expect($aws->nivel)->toBe(NivelCoincidencia::NoEncontrado)
        ->and($aws->porcentaje)->toBe(0)
        ->and($aws->evidencia)->toBeNull()
        ->and($aws->motivo)->toBe('No se encontró ninguna mención de Certificación AWS en el CV.');
});

it('busca palabras completas, sin tildes y con sinónimos', function () {
    $resultado = evaluar(CV_ELENA, [
        requisito(1, 'SQL'),                    // solo aparece dentro de "PostgreSQL"
        requisito(2, 'Postgres', ['postgresql']),
        requisito(3, 'Arquitectura en la nube'),
    ]);

    expect($resultado->requisitos[0]->nivel)->toBe(NivelCoincidencia::NoEncontrado)
        ->and($resultado->requisitos[1]->nivel)->toBe(NivelCoincidencia::Coincide)
        ->and($resultado->requisitos[2]->evidencia)->toContain('arquitectura en la nube');
});

it('pondera el puntaje global por el peso de cada requisito', function () {
    $resultado = evaluar(CV_ELENA, [requisito(1, 'PostgreSQL', peso: 3), requisito(2, 'Terraform', peso: 1)]);

    expect($resultado->score)->toBe(round($resultado->requisitos[0]->porcentaje * 3 / 4, 2))
        ->and($resultado->motor)->toBe(MotorMatching::Fake)
        ->and($resultado->resumen)->toStartWith('Coincide en 1 de 2 requisitos');
});

it('serializa el resultado sin perder información', function () {
    $resultado = evaluar(CV_ELENA, [requisito(1, 'Java'), requisito(2, 'Kubernetes'), requisito(3, 'Go')]);

    expect(MatchResult::desdeArreglo($resultado->aArreglo()))->toEqual($resultado)
        ->and($resultado->aArreglo())->toHaveKeys(['version', 'score', 'motor', 'resumen', 'requisitos']);
});

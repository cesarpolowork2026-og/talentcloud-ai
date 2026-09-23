<?php

namespace App\Providers;

use App\Repositories\Contracts\CandidatoRepository;
use App\Repositories\Contracts\EmpresaRepository;
use App\Repositories\Contracts\EquipoRepository;
use App\Repositories\Eloquent\EloquentCandidatoRepository;
use App\Repositories\Eloquent\EloquentEmpresaRepository;
use App\Repositories\Eloquent\EloquentEquipoRepository;
use Illuminate\Support\ServiceProvider;

/** Enlaza cada interfaz de repositorio con su implementación Eloquent. */
class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        EmpresaRepository::class => EloquentEmpresaRepository::class,
        CandidatoRepository::class => EloquentCandidatoRepository::class,
        EquipoRepository::class => EloquentEquipoRepository::class,
    ];
}

<?php

namespace App\Providers;

use App\Repositories\Contracts\CandidatoRepository;
use App\Repositories\Contracts\EmpresaRepository;
use App\Repositories\Eloquent\EloquentCandidatoRepository;
use App\Repositories\Eloquent\EloquentEmpresaRepository;
use Illuminate\Support\ServiceProvider;

/** Enlaza cada interfaz de repositorio con su implementación Eloquent. */
class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        EmpresaRepository::class => EloquentEmpresaRepository::class,
        CandidatoRepository::class => EloquentCandidatoRepository::class,
    ];
}

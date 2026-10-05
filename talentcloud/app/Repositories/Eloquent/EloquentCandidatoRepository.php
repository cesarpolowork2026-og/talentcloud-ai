<?php

namespace App\Repositories\Eloquent;

use App\Models\Candidato;
use App\Models\Postulacion;
use App\Models\User;
use App\Repositories\Contracts\CandidatoRepository;
use App\Tenancy\EmpresaScope;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;

class EloquentCandidatoRepository implements CandidatoRepository
{
    public function __construct(private readonly TenantContext $contexto) {}

    public function crear(array $datos): Candidato
    {
        return Candidato::create($datos);
    }

    public function deUsuario(User $user): ?Candidato
    {
        return Candidato::where('user_id', $user->id)->first();
    }

    public function visibles(): Builder
    {
        $consulta = Candidato::query();

        if ($this->contexto->esGlobal()) {
            return $consulta;
        }

        if (! $this->contexto->tieneEmpresa()) {
            return $consulta->whereRaw('1 = 0');
        }

        $empresaId = (int) $this->contexto->id();

        return $consulta->where(fn (Builder $q) => $this->condicionDeVisibilidad($q, $empresaId));
    }

    public function consultaListado(): Builder
    {
        $global = $this->contexto->esGlobal();

        // La subconsulta de Postulacion ya va filtrada por la empresa del contexto. En la
        // vista global un candidato puede tener procesos en varias empresas: se ordena por
        // el mejor match; en una empresa, por el de su última postulación.
        $matchDeOrden = $global
            ? Postulacion::query()
                ->selectRaw('max(match_score)')
                ->whereColumn('postulaciones.candidato_id', 'candidatos.id')
            : Postulacion::query()
                ->select('match_score')
                ->whereColumn('postulaciones.candidato_id', 'candidatos.id')
                ->latest('postulaciones.created_at')
                ->latest('postulaciones.id')
                ->limit(1);

        // coalesce por fuera: sin postulaciones la subconsulta no devuelve filas (NULL)
        // y quedaría primera al ordenar de mayor a menor.
        return $this->visibles()
            ->select('candidatos.*')
            ->selectRaw('coalesce(('.$matchDeOrden->toSql().'), -1) as match_de_orden', $matchDeOrden->getBindings())
            ->with(['ultimaPostulacion.vacante.empresa:id,nombre', 'cvActual', 'competencias'])
            // Vista global (admin SaaS): todos sus procesos, agrupados por empresa en la tarjeta.
            ->when($global, fn (Builder $q) => $q->with(['postulaciones' => fn ($p) => $p->with('vacante.empresa:id,nombre')->latest()]));
    }

    public function buscarVisible(int $id): ?Candidato
    {
        return $this->visibles()->whereKey($id)->first();
    }

    public function esVisibleParaEmpresa(Candidato $candidato, int $empresaId): bool
    {
        return Candidato::query()
            ->whereKey($candidato->id)
            ->where(fn (Builder $q) => $this->condicionDeVisibilidad($q, $empresaId))
            ->exists();
    }

    /**
     * @param  Builder<Candidato>  $q
     */
    private function condicionDeVisibilidad(Builder $q, int $empresaId): void
    {
        $q->where('candidatos.empresa_origen_id', $empresaId)
            ->orWhereHas('postulaciones', fn (Builder $p) => $p
                ->withoutGlobalScope(EmpresaScope::class)
                ->where('postulaciones.empresa_id', $empresaId));
    }
}

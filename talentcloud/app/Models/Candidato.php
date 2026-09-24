<?php

namespace App\Models;

use App\Enums\FuenteCompetencia;
use App\Tenancy\EmpresaScope;
use App\Tenancy\TenantContext;
use Database\Factories\CandidatoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Persona candidata. Es global (sin BelongsToEmpresa): un postulante aplica a
 * varias empresas. La visibilidad por empresa se aplica en su repositorio y
 * en su Policy (postuló a una vacante de la empresa o la empresa lo registró).
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $empresa_origen_id
 * @property string $nombre
 * @property Carbon|null $consentimiento_at
 */
class Candidato extends Model
{
    /** @use HasFactory<CandidatoFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'empresa_origen_id',
        'nombre',
        'email',
        'telefono',
        'titular',
        'consentimiento_at',
    ];

    protected function casts(): array
    {
        return [
            'consentimiento_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresaOrigen(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_origen_id');
    }

    /**
     * Cuenta de postulante, si el candidato se registró por su cuenta.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Cv, $this>
     */
    public function cvs(): HasMany
    {
        return $this->hasMany(Cv::class);
    }

    /**
     * CV más reciente.
     *
     * @return HasOne<Cv, $this>
     */
    public function cvActual(): HasOne
    {
        return $this->hasOne(Cv::class)->latestOfMany();
    }

    /**
     * Competencias visibles en el contexto actual: las extraídas del CV por la
     * IA y las manuales de la empresa en curso (el admin SaaS ve todas). Las
     * manuales son anotaciones de cada empresa sobre un candidato global.
     *
     * @return BelongsToMany<Competencia, $this>
     */
    public function competencias(): BelongsToMany
    {
        $contexto = app(TenantContext::class);
        $relacion = $this->todasLasCompetencias();

        if ($contexto->esGlobal()) {
            return $relacion;
        }

        return $relacion->where(fn (Builder $q) => $q
            ->where('candidato_competencia.fuente', FuenteCompetencia::Extraccion->value)
            ->when($contexto->tieneEmpresa(), fn (Builder $q) => $q->orWhere('candidato_competencia.empresa_id', $contexto->id())));
    }

    /**
     * Todas sus competencias, de cualquier empresa. Para los servicios que las
     * modifican (que acotan por fuente y empresa) y tareas de plataforma.
     *
     * @return BelongsToMany<Competencia, $this>
     */
    public function todasLasCompetencias(): BelongsToMany
    {
        return $this->belongsToMany(Competencia::class, 'candidato_competencia')
            ->withPivot(['fuente', 'empresa_id', 'anios', 'evidencia'])
            ->withTimestamps();
    }

    /**
     * Postulaciones visibles en el contexto actual (las de la empresa en curso).
     *
     * @return HasMany<Postulacion, $this>
     */
    public function postulaciones(): HasMany
    {
        return $this->hasMany(Postulacion::class);
    }

    /**
     * Postulación más reciente visible en el contexto actual ("Postuló a …").
     *
     * @return HasOne<Postulacion, $this>
     */
    public function ultimaPostulacion(): HasOne
    {
        return $this->hasOne(Postulacion::class)->latestOfMany();
    }

    /**
     * Todas sus postulaciones, en cualquier empresa. Solo para el propio
     * postulante ("Mis postulaciones") o tareas de plataforma.
     *
     * @return HasMany<Postulacion, $this>
     */
    public function postulacionesEnTodasLasEmpresas(): HasMany
    {
        return $this->hasMany(Postulacion::class)->withoutGlobalScope(EmpresaScope::class);
    }
}

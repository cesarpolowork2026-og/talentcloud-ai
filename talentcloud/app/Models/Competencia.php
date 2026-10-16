<?php

namespace App\Models;

use Database\Factories\CompetenciaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Competencia del catálogo compartido (Java, Figma, SQL…).
 *
 * @property int $id
 * @property string $nombre
 * @property string $nombre_normalizado
 * @property string|null $categoria
 * @property list<string> $sinonimos
 */
class Competencia extends Model
{
    /** @use HasFactory<CompetenciaFactory> */
    use HasFactory;

    private const CLAVE_NOMBRES = 'competencias:nombres';

    private const CLAVE_CATALOGO = 'competencias:catalogo';

    protected $table = 'competencias';

    protected $fillable = ['nombre', 'categoria', 'sinonimos'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'sinonimos' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'sinonimos' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Competencia $competencia): void {
            $competencia->nombre = trim($competencia->nombre);
            $competencia->nombre_normalizado = self::normalizar($competencia->nombre);
        });

        static::saved(fn () => self::olvidarCatalogo());
        static::deleted(fn () => self::olvidarCatalogo());
    }

    /**
     * Nombres del catálogo, para los `<datalist>`. Cambia casi nunca y se pedía en cada
     * render de Livewire: se recuerda una hora y se olvida al guardar o borrar una competencia.
     *
     * @return list<string>
     */
    public static function nombres(): array
    {
        return Cache::remember(self::CLAVE_NOMBRES, 3600, fn () => self::orderBy('nombre')->pluck('nombre')->all());
    }

    /**
     * Catálogo completo (nombre, categoría y sinónimos) para el selector de competencias.
     *
     * @return list<array{nombre: string, categoria: string|null, sinonimos: list<string>}>
     */
    public static function catalogoCompleto(): array
    {
        return Cache::remember(self::CLAVE_CATALOGO, 3600, fn () => self::orderBy('nombre')->get(['nombre', 'categoria', 'sinonimos'])
            ->map(fn (self $c) => ['nombre' => $c->nombre, 'categoria' => $c->categoria, 'sinonimos' => $c->sinonimos])
            ->all());
    }

    public static function olvidarCatalogo(): void
    {
        Cache::forget(self::CLAVE_NOMBRES);
        Cache::forget(self::CLAVE_CATALOGO);
    }

    /** Clave de unicidad: minúsculas, sin tildes ni espacios repetidos. */
    public static function normalizar(string $nombre): string
    {
        return Str::of($nombre)->ascii()->lower()->squish()->toString();
    }

    /**
     * La competencia del catálogo que corresponde a ese texto: por nombre o por uno de
     * sus sinónimos ("PowerBI" → Power BI). Sin tildes ni diferencia de mayúsculas.
     */
    public static function resolver(string $nombre): ?self
    {
        $clave = self::normalizar($nombre);

        return self::where('nombre_normalizado', $clave)->first()
            ?? self::whereRaw(
                'exists (select 1 from jsonb_array_elements_text(sinonimos) as s(valor) where lower(unaccent(s.valor)) = ?)',
                [$clave],
            )->first();
    }

    /** Busca por nombre o sinónimo, o la crea ("alta rápida" del editor de requisitos). */
    public static function obtenerOCrear(string $nombre, ?string $categoria = null): self
    {
        return self::resolver($nombre) ?? self::firstOrCreate(
            ['nombre_normalizado' => self::normalizar($nombre)],
            ['nombre' => $nombre, 'categoria' => $categoria],
        );
    }
}

<?php

namespace App\Models;

use Database\Factories\CompetenciaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Competencia del catálogo compartido (Java, Figma, SQL…).
 *
 * @property int $id
 * @property string $nombre
 * @property string $nombre_normalizado
 * @property list<string> $sinonimos
 */
class Competencia extends Model
{
    /** @use HasFactory<CompetenciaFactory> */
    use HasFactory;

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
    }

    /** Clave de unicidad: minúsculas, sin tildes ni espacios repetidos. */
    public static function normalizar(string $nombre): string
    {
        return Str::of($nombre)->ascii()->lower()->squish()->toString();
    }

    /** Busca por nombre normalizado o la crea ("alta rápida" del editor de requisitos). */
    public static function obtenerOCrear(string $nombre, ?string $categoria = null): self
    {
        return self::firstOrCreate(
            ['nombre_normalizado' => self::normalizar($nombre)],
            ['nombre' => $nombre, 'categoria' => $categoria],
        );
    }
}

<?php

namespace Tests\Fixtures;

use App\Tenancy\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo de prueba con BelongsToEmpresa, para verificar el aislamiento entre
 * empresas sin depender de un módulo concreto. Su tabla se crea en cada test
 * (dentro de la transacción de RefreshDatabase).
 */
class RegistroDeEmpresa extends Model
{
    use BelongsToEmpresa;

    protected $table = 'registros_de_empresa';

    protected $fillable = ['titulo', 'empresa_id'];

    public static function crearTabla(): void
    {
        Schema::create('registros_de_empresa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('titulo');
            $table->timestamps();
        });
    }
}

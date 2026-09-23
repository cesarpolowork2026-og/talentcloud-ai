<?php

use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Los cuatro roles de la plataforma son datos estructurales (el login, la
 * navegación y las Policies dependen de ellos), por eso se crean en una
 * migración y existen en todos los entornos, incluidos los tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        $guard = config('auth.defaults.guard');
        $ahora = now();

        foreach (Rol::cases() as $rol) {
            DB::table(config('permission.table_names.roles'))->insertOrIgnore([
                'name' => $rol->value,
                'guard_name' => $guard,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        app()['cache']->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.roles'))
            ->whereIn('name', array_column(Rol::cases(), 'value'))
            ->delete();

        app()['cache']->forget(config('permission.cache.key'));
    }
};

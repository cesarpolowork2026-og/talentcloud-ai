<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Una vacante remota no tiene ubicación: solo una cobertura opcional ("Perú", "LATAM").
 * Las que guardaron la palabra "Remoto" como ubicación quedan sin ella.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('vacantes')
            ->where('modalidad', 'remoto')
            ->whereRaw("lower(trim(ubicacion)) = 'remoto'")
            ->update(['ubicacion' => null]);
    }

    public function down(): void
    {
        // No se restaura: «Remoto» como ubicación era un dato redundante.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * El Libro de Reclamaciones queda fuera del alcance de la primera unidad: se retira su tabla. El código completo
 * (formulario público, atención por el administrador, correos y pruebas) se conserva en la rama
 * `libro-de-reclamaciones` para retomarlo en una unidad posterior.
 *
 * Los avisos que ya se guardaron sobre hojas se borran: apuntan a pantallas que dejan de existir.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notifications')->where('data', 'like', '%Libro de Reclamaciones%')->delete();
        Schema::dropIfExists('reclamaciones');
    }

    public function down(): void
    {
        // Vuelve la estructura (vacía) con la migración que la creó.
        (include __DIR__.'/2026_10_09_100000_crear_reclamaciones.php')->up();
    }
};

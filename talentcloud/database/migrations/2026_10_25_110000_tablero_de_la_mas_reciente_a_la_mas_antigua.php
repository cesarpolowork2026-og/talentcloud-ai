<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Desde ahora una postulación nueva (o movida con «Mover a…») entra arriba de su columna del tablero. Las que ya
 * existen se ordenan una vez igual: en cada columna, la que llegó a esa etapa más recientemente primero. Solo cambia la
 * posición (`orden`): etapas, historial, avisos y fechas quedan como estaban. No hay vuelta atrás que tenga sentido
 * (el orden anterior era el de llegada al final de la columna), así que down() no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE postulaciones p
            SET orden = n.nuevo
            FROM (
                SELECT id, ROW_NUMBER() OVER (
                    PARTITION BY vacante_id, etapa
                    ORDER BY COALESCE(etapa_cambiada_at, created_at) DESC, id DESC
                ) - 1 AS nuevo
                FROM postulaciones
            ) n
            WHERE p.id = n.id AND p.orden <> n.nuevo
            SQL);
    }

    public function down(): void
    {
        //
    }
};

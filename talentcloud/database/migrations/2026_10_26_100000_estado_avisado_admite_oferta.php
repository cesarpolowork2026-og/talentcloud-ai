<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * La Oferta deja de ser interna: se le avisa al postulante y se guarda como el último estado avisado.
 */
return new class extends Migration
{
    private const ANTES = ['postulado', 'en_revision', 'entrevista', 'contratado', 'no_seleccionado', 'cerrada', 'retirada'];

    public function up(): void
    {
        $this->restriccion([...self::ANTES, 'oferta']);
    }

    public function down(): void
    {
        DB::table('postulaciones')->where('estado_avisado', 'oferta')->update(['estado_avisado' => 'entrevista']);
        $this->restriccion(self::ANTES);
    }

    /** @param  list<string>  $valores */
    private function restriccion(array $valores): void
    {
        $lista = implode(', ', array_map(fn (string $v) => "'{$v}'", $valores));

        DB::statement('ALTER TABLE postulaciones DROP CONSTRAINT IF EXISTS postulaciones_estado_avisado_check');
        DB::statement("ALTER TABLE postulaciones ADD CONSTRAINT postulaciones_estado_avisado_check CHECK (estado_avisado IS NULL OR estado_avisado IN ({$lista}))");
    }
};

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/*
 * Sin WithoutModelEvents: los eventos de los modelos aplican las reglas del
 * tenant (BelongsToEmpresa) y normalizan las competencias.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogoCompetenciasSeeder::class,
            DemoSeeder::class,
        ]);
    }
}

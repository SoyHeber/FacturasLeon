<?php

namespace Database\Seeders;

use App\Models\TipoDocumento;
use Illuminate\Database\Seeder;

class TipoDocumentoSeeder extends Seeder
{
    public function run(): void
    {
        TipoDocumento::updateOrCreate(
            ['codigo' => 'FACT'],
            ['codigo' => 'FACT', 'nombre' => 'Factura', 'estado' => true]
        );
    }
}

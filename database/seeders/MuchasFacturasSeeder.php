<?php

namespace Database\Seeders;

use App\Models\Facturas;
use Illuminate\Database\Seeder;

class MuchasFacturasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Facturas::factory()->count(50)->create();
    }
}

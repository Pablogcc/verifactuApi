<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CiudadesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('poblaciones')->insert([
            'codigo_x' => '1',
            'cp' => '03301',
            'poblacion' => 'Delete',
            'provincia_nombre' => 'provincia_Delete',
            'pais' => '48'
        ]);
    }
}

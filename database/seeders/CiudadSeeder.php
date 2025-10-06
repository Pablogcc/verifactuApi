<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CiudadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('poblaciones')->insert([
            'codigo_x' => '9999999',
            'cp' => '00001',
            'poblacion' => 'Deleted',
            'provincia_nombre' => 'Deleted',
            'pais' => '34'
        ]);
    }
}
